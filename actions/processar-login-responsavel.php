<?php
session_start();

$servidor = "localhost";
$usuario  = "root";
$senha_db = "";
$banco    = "banco-eep";

$conexao = new mysqli($servidor, $usuario, $senha_db, $banco);
if ($conexao->connect_error) {
    die("Falha na conexão: " . $conexao->connect_error);
}
$conexao->set_charset("utf8mb4");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cpfDigitado = trim($_POST['cpf'] ?? '');
    $cpfLimpo    = preg_replace('/\D/', '', $cpfDigitado);
    $senha       = trim($_POST['senha'] ?? '');

    // Formata com pontos e traço caso esteja salvo assim no banco
    $cpfFormatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfLimpo);

    // Busca pelo CPF (tanto limpo quanto formatado)
    $stmt = $conexao->prepare("SELECT id, nome_completo, cpf, senha FROM responsaveis WHERE cpf = ? OR cpf = ? LIMIT 1");
    $stmt->bind_param("ss", $cpfLimpo, $cpfFormatado);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($responsavel = $resultado->fetch_assoc()) {
        // Verifica se a senha bate (seja hash criptografado ou texto simples)
        if (password_verify($senha, $responsavel['senha']) || $senha === $responsavel['senha']) {
            $_SESSION['responsavel_id']   = $responsavel['id'];
            $_SESSION['responsavel_nome'] = $responsavel['nome_completo'];
            $_SESSION['responsavel_cpf']  = $responsavel['cpf'];

            header("Location: ../responsavel/painel-responsavel.php");
            exit();
        }
    }

    echo "<script>alert('CPF ou senha incorretos!'); window.location.href='../responsavel/login-responsavel.php';</script>";
    $stmt->close();
}

$conexao->close();
?>

