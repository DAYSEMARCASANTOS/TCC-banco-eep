<?php
session_start();
require_once '../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../estudante/perfil.php");
    exit();
}

$aluno_id = $_SESSION['usuario_id'] ?? null;
if (!$aluno_id) {
    echo "<script>alert('Sessão expirada. Faça login novamente.'); window.location.href='../estudante/login-estudante.php';</script>";
    exit();
}

$nome_resp = trim($_POST['nome_responsavel'] ?? '');
$cpf_resp  = preg_replace('/\D/', '', $_POST['cpf_responsavel'] ?? '');

if (empty($nome_resp) || strlen($cpf_resp) !== 11) {
    echo "<script>alert('Preencha o nome completo e um CPF válido (11 dígitos).'); history.back();</script>";
    exit();
}

try {
    // Busca usando o nome exato da coluna ('nome_completo') da sua tabela 'estudantes'
    $stmtAluno = $pdo->prepare("SELECT nome_completo, cpf FROM estudantes WHERE id = ? LIMIT 1");
    $stmtAluno->execute([$aluno_id]);
    $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);

    $nome_filho = $aluno['nome_completo'] ?? 'Estudante';
    $cpf_filho  = preg_replace('/\D/', '', $aluno['cpf'] ?? '');

    $cpf_formatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf_resp);
    $stmtBuscaResp = $pdo->prepare("SELECT id FROM responsaveis WHERE cpf = ? OR cpf = ? LIMIT 1");
    $stmtBuscaResp->execute([$cpf_resp, $cpf_formatado]);
    $respExistente = $stmtBuscaResp->fetch(PDO::FETCH_ASSOC);

    if ($respExistente) {
        $responsavel_id = $respExistente['id'];
    } else {
        $senhaPadrao = password_hash('123456', PASSWORD_DEFAULT);
        $stmtCriaResp = $pdo->prepare("INSERT INTO responsaveis (nome, cpf, telefone, senha) VALUES (?, ?, '', ?)");
        $stmtCriaResp->execute([$nome_resp, $cpf_resp, $senhaPadrao]);
        $responsavel_id = $pdo->lastInsertId();
    }

    $stmtVinculo = $pdo->prepare("INSERT INTO responsavel_filhos (responsavel_id, nome_filho, cpf_filho) VALUES (?, ?, ?)");
    $stmtVinculo->execute([$responsavel_id, $nome_filho, $cpf_filho]);

    echo "<script>
        alert('Responsável vinculado com sucesso! (Senha provisória para primeiro acesso: 123456)');
        window.location.href = '../estudante/perfil.php';
    </script>";
    exit();

} catch (PDOException $e) {
    echo "<script>
        alert('Erro ao vincular responsável: " . addslashes($e->getMessage()) . "');
        history.back();
    </script>";
    exit();
}

