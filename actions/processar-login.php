<?php
session_start();

$servidor = "localhost";
$usuario  = "root";
$senha_db = "";
$banco    = "banco-eep"; // Confira se no seu phpMyAdmin está 'banco-eep' ou 'banco_eep'

$conexao = new mysqli($servidor, $usuario, $senha_db, $banco);

if ($conexao->connect_error) {
    die("Falha na conexão: " . $conexao->connect_error);
}

$conexao->set_charset("utf8mb4");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matricula = trim($_POST['matricula']);
    $senha     = trim($_POST['senha']);

    $stmt = $conexao->prepare("SELECT id, nome_completo, ra FROM estudantes WHERE ra = ? AND senha = ?");
    $stmt->bind_param("ss", $matricula, $senha);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $aluno = $resultado->fetch_assoc();
        
        // Grava exatamente as duas chaves que o painel.php exige
        $_SESSION['usuario_id'] = $aluno['id'];
        $_SESSION['ra']         = $aluno['ra'];

        header("Location: painel.php");
        exit();
    } else {
        echo "<script>alert('RA ou senha incorretos!'); window.location.href='login-estudante.php';</script>";
    }

    $stmt->close();
}

$conexao->close();
?>
