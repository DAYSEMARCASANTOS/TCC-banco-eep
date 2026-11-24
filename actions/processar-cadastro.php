<?php
$servidor = "localhost";
$usuario  = "root";
$senha_db = "";
$banco    = "banco-eep";

$conexao = new mysqli($servidor, $usuario, $senha_db, $banco);
if ($conexao->connect_error) {
    die("Falha na conexão com o banco: " . $conexao->connect_error);
}
$conexao->set_charset("utf8mb4");

// Garante que a coluna 'foto' exista na tabela estudantes
$check_col = $conexao->query("SHOW COLUMNS FROM estudantes LIKE 'foto'");
if ($check_col && $check_col->num_rows === 0) {
    $conexao->query("ALTER TABLE estudantes ADD COLUMN foto VARCHAR(255) DEFAULT NULL");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = trim($_POST['nome_completo'] ?? '');

    if (isset($_POST['responsavel']) && is_array($_POST['responsavel'])) {
        $lista_limpa = array_filter(array_map('trim', $_POST['responsavel']));
        $responsavel = implode(', ', $lista_limpa);
    } else {
        $responsavel = trim($_POST['responsavel'] ?? '');
    }

    $escola    = trim($_POST['escola'] ?? '');
    $cpf       = trim($_POST['cpf'] ?? '');
    $matricula = trim($_POST['matricula'] ?? '');
    $senha     = trim($_POST['senha'] ?? '');

    $saldo_inicial  = 47.50;
    $limite_inicial = 20.00;

    // 1. Verifica duplicidade de RA ou CPF
    $verifica = $conexao->prepare("SELECT id FROM estudantes WHERE ra = ? OR cpf = ?");
    $verifica->bind_param("ss", $matricula, $cpf);
    $verifica->execute();
    $verifica->store_result();

    if ($verifica->num_rows > 0) {
        echo "<script>alert('Este RA ou CPF já está cadastrado! Tente fazer login.'); window.location.href='login-estudante.php';</script>";
        $verifica->close();
        $conexao->close();
        exit;
    }
    $verifica->close();

    // 2. Processa a foto (Galeria OU Webcam)
    $caminho_foto = null;
    $pasta_uploads = "uploads/";
    if (!is_dir($pasta_uploads)) {
        mkdir($pasta_uploads, 0777, true);
    }

    // Caso A: Enviou arquivo pela Galeria / Drive
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $permitidas)) {
            $nome_arquivo = "aluno_" . preg_replace('/\D/', '', $matricula) . "_" . time() . "." . $ext;
            $destino = $pasta_uploads . $nome_arquivo;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $caminho_foto = $destino;
            }
        }
    }
    // Caso B: Tirou foto na hora pela Câmera (Base64)
    elseif (!empty($_POST['foto_camera'])) {
        $dados_img = $_POST['foto_camera'];
        if (preg_match('/^data:image\/(\w+);base64,/', $dados_img, $tipo)) {
            $dados_img = substr($dados_img, strpos($dados_img, ',') + 1);
            $dados_binarios = base64_decode($dados_img);
            if ($dados_binarios !== false) {
                $nome_arquivo = "aluno_" . preg_replace('/\D/', '', $matricula) . "_" . time() . ".jpg";
                $destino = $pasta_uploads . $nome_arquivo;
                if (file_put_contents($destino, $dados_binarios)) {
                    $caminho_foto = $destino;
                }
            }
        }
    }

    // 3. Insere o estudante com o caminho da foto
    $stmt = $conexao->prepare("INSERT INTO estudantes (nome_completo, responsavel, escola, cpf, ra, senha, saldo, limite_diario, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssdds", $nome_completo, $responsavel, $escola, $cpf, $matricula, $senha, $saldo_inicial, $limite_inicial, $caminho_foto);

    if ($stmt->execute()) {
        echo "<script>alert('Conta criada com sucesso! Faça login com seu RA e senha.'); window.location.href='login-estudante.php';</script>";
        exit;
    } else {
        echo "<script>alert('Erro ao salvar no banco: " . addslashes($stmt->error) . "'); window.history.back();</script>";
    }

    $stmt->close();
}

$conexao->close();
?>


