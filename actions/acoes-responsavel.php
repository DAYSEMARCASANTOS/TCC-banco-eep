<?php
session_start();

if (!isset($_SESSION['responsavel_id'])) {
    header("Location: ../responsavel/login-responsavel.php");
    exit();
}

$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "banco-eep";

$conn = new mysqli($host, $usuario, $senha, $banco);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$responsavel_id   = $_SESSION['responsavel_id'];
$responsavel_nome = $_SESSION['responsavel_nome'] ?? 'Responsável';
$acao             = $_POST['acao'] ?? '';

// 1. RECARREGAR SALDO DO DEPENDENTE
if ($acao === 'recarregar') {
    $ra    = trim($_POST['ra'] ?? '');
    $valor = (float) str_replace(',', '.', $_POST['valor'] ?? 0);

    if (empty($ra) || $valor <= 0) {
        echo "<script>alert('Informe um valor válido maior que zero.'); window.location.href='../responsavel/painel-responsavel.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Soma o valor ao saldo do estudante
        $stmtUpd = $conn->prepare("UPDATE estudantes SET saldo = saldo + ? WHERE ra = ?");
        $stmtUpd->bind_param("ds", $valor, $ra);
        $stmtUpd->execute();
        $stmtUpd->close();

        // Registra a transação no extrato do estudante
        $descricao = "Recarga via Responsável (" . explode(' ', trim($responsavel_nome)) . ")";
        $categoria = "Recarga";
        $tipo      = "entrada";
        $stmtTrans = $conn->prepare("INSERT INTO transacoes (ra, descricao, categoria, valor, tipo, data_hora) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmtTrans->bind_param("sssds", $ra, $descricao, $categoria, $valor, $tipo);
        $stmtTrans->execute();
        $stmtTrans->close();

        $conn->commit();
        echo "<script>alert('Recarga de R$ " . number_format($valor, 2, ',', '.') . " realizada com sucesso!'); window.location.href='../responsavel/painel-responsavel.php';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Erro ao realizar recarga: " . addslashes($e->getMessage()) . "'); window.location.href='../responsavel/painel-responsavel.php';</script>";
    }
    exit();
}

// 2. ALTERAR LIMITE DIÁRIO
if ($acao === 'limite') {
    $ra     = trim($_POST['ra'] ?? '');
    $limite = (float) str_replace(',', '.', $_POST['limite_diario'] ?? 0);

    if (empty($ra) || $limite < 0) {
        echo "<script>alert('Informe um limite válido.'); window.location.href='../responsavel/painel-responsavel.php';</script>";
        exit();
    }

    $stmtLim = $conn->prepare("UPDATE estudantes SET limite_diario = ? WHERE ra = ?");
    $stmtLim->bind_param("ds", $limite, $ra);
    $stmtLim->execute();
    $stmtLim->close();

    echo "<script>alert('Limite diário atualizado para R$ " . number_format($limite, 2, ',', '.') . "!'); window.location.href='../responsavel/painel-responsavel.php';</script>";
    exit();
}

// 3. VINCULAR OUTRO DEPENDENTE PELO RA
if ($acao === 'vincular') {
    $ra = trim($_POST['ra'] ?? '');

    if (empty($ra)) {
        echo "<script>alert('Digite o RA do estudante.'); window.location.href='../responsavel/painel-responsavel.php';</script>";
        exit();
    }

    $stmtEst = $conn->prepare("SELECT nome_completo, cpf FROM estudantes WHERE ra = ?");
    $stmtEst->bind_param("s", $ra);
    $stmtEst->execute();
    $est = $stmtEst->get_result()->fetch_assoc();
    $stmtEst->close();

    if (!$est) {
        echo "<script>alert('Nenhum estudante encontrado com o RA $ra.'); window.location.href='../responsavel/painel-responsavel.php';</script>";
        exit();
    }

    $cpf_limpo = preg_replace('/\D/', '', $est['cpf']);

    $stmtCheck = $conn->prepare("SELECT id FROM responsavel_filhos WHERE responsavel_id = ? AND cpf_filho = ?");
    $stmtCheck->bind_param("is", $responsavel_id, $cpf_limpo);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        echo "<script>alert('Este dependente já está vinculado à sua conta.'); window.location.href='../responsavel/painel-responsavel.php';</script>";
        exit();
    }
    $stmtCheck->close();

    $stmtIns = $conn->prepare("INSERT INTO responsavel_filhos (responsavel_id, nome_filho, cpf_filho) VALUES (?, ?, ?)");
    $stmtIns->bind_param("iss", $responsavel_id, $est['nome_completo'], $cpf_limpo);
    $stmtIns->execute();
    $stmtIns->close();

    echo "<script>alert('Dependente " . addslashes($est['nome_completo']) . " vinculado com sucesso!'); window.location.href='../responsavel/painel-responsavel.php';</script>";
    exit();
}

header("Location: ../responsavel/painel-responsavel.php");
exit();
?>
