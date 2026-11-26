<?php
session_start();

$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "banco-eep";

$conn = new mysqli($host, $usuario, $senha, $banco);
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ra_aluno = $_SESSION['ra'] ?? ''; 
    $nome_resp = trim($_POST['nome_responsavel'] ?? '');
    $cpf_resp = trim($_POST['cpf_responsavel'] ?? '');

    if (empty($ra_aluno) || empty($nome_resp) || empty($cpf_resp)) {
        echo "<script>alert('Erro: Sessão expirada ou campos vazios.'); history.back();</script>";
        exit;
    }

    // 1. Busca o nome e o CPF do estudante logado pelo RA
    $stmtEst = $conn->prepare("SELECT nome_completo, cpf FROM estudantes WHERE ra = ?");
    $stmtEst->bind_param("s", $ra_aluno);
    $stmtEst->execute();
    $dadosEst = $stmtEst->get_result()->fetch_assoc();
    $stmtEst->close();

    if (!$dadosEst) {
        echo "<script>alert('Estudante não encontrado.'); history.back();</script>";
        exit;
    }

    $nome_filho = $dadosEst['nome_completo'];
    $cpf_filho_limpo = preg_replace('/\D/', '', $dadosEst['cpf']); // Salva só os números igual ao seu banco

    // 2. Verifica se o responsável já existe na tabela 'responsaveis'
    $stmt = $conn->prepare("SELECT id FROM responsaveis WHERE cpf = ?");
    $stmt->bind_param("s", $cpf_resp);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $responsavel_id = $row['id'];
    } else {
        $senhaHash = password_hash('123456', PASSWORD_DEFAULT);
        $stmtIns = $conn->prepare("INSERT INTO responsaveis (nome_completo, cpf, ra_estudante, senha) VALUES (?, ?, ?, ?)");
        $stmtIns->bind_param("ssss", $nome_resp, $cpf_resp, $ra_aluno, $senhaHash);
        $stmtIns->execute();
        $responsavel_id = $stmtIns->insert_id;
        $stmtIns->close();
    }
    $stmt->close();

    // 3. Grava o vínculo na tabela 'responsavel_filhos' usando as colunas exatas (responsavel_id, nome_filho, cpf_filho)
    $stmtCheck = $conn->prepare("SELECT id FROM responsavel_filhos WHERE responsavel_id = ? AND cpf_filho = ?");
    $stmtCheck->bind_param("is", $responsavel_id, $cpf_filho_limpo);
    $stmtCheck->execute();
    
    if ($stmtCheck->get_result()->num_rows === 0) {
        $stmtVinc = $conn->prepare("INSERT INTO responsavel_filhos (responsavel_id, nome_filho, cpf_filho) VALUES (?, ?, ?)");
        $stmtVinc->bind_param("iss", $responsavel_id, $nome_filho, $cpf_filho_limpo);
        $stmtVinc->execute();
        $stmtVinc->close();
    }
    $stmtCheck->close();

    // 4. Retorna automaticamente para a página de perfil
    echo "<script>
        alert('Responsável vinculado com sucesso! (Senha provisória para primeiro acesso: 123456)');
        window.location.href = '../estudante/perfil.php';
    </script>";
    exit;
}
?>

