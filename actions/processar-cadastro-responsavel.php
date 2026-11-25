<?php
session_start();
require_once '../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../responsavel/cadastro-responsavel.php");
    exit();
}

// Função auxiliar para padronizar CPF apenas com números (evita duplicidade por pontuação)
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

// 2. Filtra e remove filhos duplicados antes mesmo de abrir transação no banco
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

// 3. Verifica se o responsável já existe
$stmtVerifica = $conexao->prepare("SELECT id FROM responsaveis WHERE cpf = ? LIMIT 1");
$stmtVerifica->bind_param("s", $cpf);
$stmtVerifica->execute();
if ($stmtVerifica->get_result()->num_rows > 0) {
    echo "<script>alert('Este CPF já possui cadastro! Faça login.'); window.location.href='../responsavel/login-responsavel.php';</script>";
    exit();
}
$stmtVerifica->close();

// 4. Grava tudo em lote dentro de uma transação
$conexao->begin_transaction();

try {
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $stmtResp = $conexao->prepare("INSERT INTO responsaveis (nome, cpf, telefone, senha) VALUES (?, ?, ?, ?)");
    $stmtResp->bind_param("ssss", $nome, $cpf, $telefone, $senhaHash);
    $stmtResp->execute();
    $responsavel_id = $conexao->insert_id;
    $stmtResp->close();

    // Prepared statement reutilizado no loop (alta performance para 6, 10 ou 50 filhos)
    $stmtFilho = $conexao->prepare("INSERT INTO responsavel_filhos (responsavel_id, nome_filho, cpf_filho) VALUES (?, ?, ?)");
    foreach ($filhosValidos as $filho) {
        $stmtFilho->bind_param("iss", $responsavel_id, $filho['nome'], $filho['cpf']);
        $stmtFilho->execute();
    }
    $stmtFilho->close();

    $conexao->commit();

    // Login automático após cadastro (mais prático que obrigar a digitar tudo de novo)
    $_SESSION['responsavel_id']   = $responsavel_id;
    $_SESSION['responsavel_nome'] = $nome;

    echo "<script>
        alert('Conta criada com " . count($filhosValidos) . " filho(s) vinculado(s)!');
        window.location.href = '../responsavel/painel-responsavel.php';
    </script>";
    exit();

} catch (Exception $e) {
    $conexao->rollback();
    echo "<script>alert('Erro ao salvar cadastro. Tente novamente.'); history.back();</script>";
    exit();
}
$conexao->close();
