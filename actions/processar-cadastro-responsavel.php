<?php
session_start();
require_once '../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../responsavel/cadastro-responsavel.php");
    exit();
}

// Função auxiliar para padronizar CPF e telefone apenas com números
function limparNumeros($valor) {
    return preg_replace('/\D/', '', $valor);
}

$nome            = trim($_POST['nome'] ?? '');
$cpf             = limparNumeros($_POST['cpf'] ?? '');
$telefone        = limparNumeros($_POST['telefone'] ?? '');
$senha           = $_POST['senha'] ?? '';
$confirmar_senha = $_POST['confirmar_senha'] ?? '';

$filhos_nomes = $_POST['filho_nome'] ?? [];
$filhos_cpfs  = $_POST['filho_cpf'] ?? [];

// 1. Validações rápidas
if (empty($nome) || strlen($cpf) !== 11 || strlen($telefone) < 10 || strlen($senha) < 6) {
    echo "<script>alert('Verifique os dados: CPF deve ter 11 dígitos e senha no mínimo 6 caracteres.'); history.back();</script>";
    exit();
}

if ($senha !== $confirmar_senha) {
    echo "<script>alert('As senhas não coincidem!'); history.back();</script>";
    exit();
}

// 2. Filtra e remove filhos duplicados antes de abrir transação no banco
$filhosValidos = [];
$cpfsVistos = [];

for ($i = 0; $i < count($filhos_nomes); $i++) {
    $nomeFilho = trim($filhos_nomes[$i] ?? '');
    $cpfFilho  = limparNumeros($filhos_cpfs[$i] ?? '');

    if (!empty($nomeFilho) && strlen($cpfFilho) === 11 && !in_array($cpfFilho, $cpfsVistos)) {
        if ($cpfFilho === $cpf) {
            echo "<script>alert('O CPF do filho não pode ser igual ao do responsável.'); history.back();</script>";
            exit();
        }
        $cpfsVistos[] = $cpfFilho;
        $filhosValidos[] = ['nome' => $nomeFilho, 'cpf' => $cpfFilho];
    }
}

if (empty($filhosValidos)) {
    echo "<script>alert('Adicione pelo menos 1 filho com nome e CPF válidos (11 dígitos).'); history.back();</script>";
    exit();
}

try {
    // 3. Verifica se o responsável já existe
    $stmtVerifica = $pdo->prepare("SELECT id FROM responsaveis WHERE cpf = ? LIMIT 1");
    $stmtVerifica->execute([$cpf]);

    if ($stmtVerifica->fetch()) {
        echo "<script>alert('Este CPF já possui cadastro! Faça login.'); window.location.href='../responsavel/login-responsavel.php';</script>";
        exit();
    }

    // 4. Grava tudo em lote dentro de uma transação
    $pdo->beginTransaction();

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $stmtResp = $pdo->prepare("INSERT INTO responsaveis (nome_completo, cpf, telefone, senha) VALUES (?, ?, ?, ?)");

    $stmtResp->execute([$nome, $cpf, $telefone, $senhaHash]);
    $responsavel_id = $pdo->lastInsertId();

    $stmtFilho = $pdo->prepare("INSERT INTO responsavel_filhos (responsavel_id, nome_filho, cpf_filho) VALUES (?, ?, ?)");
    foreach ($filhosValidos as $filho) {
        $stmtFilho->execute([$responsavel_id, $filho['nome'], $filho['cpf']]);
    }

    $pdo->commit();

    // Login automático após cadastro
    $_SESSION['responsavel_id']   = $responsavel_id;
    $_SESSION['responsavel_nome'] = $nome;

    echo "<script>
        alert('Conta criada com " . count($filhosValidos) . " filho(s) vinculado(s)!');
        window.location.href = '../responsavel/painel-responsavel.php';
    </script>";
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<script>alert('Erro ao salvar cadastro: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit();
}

