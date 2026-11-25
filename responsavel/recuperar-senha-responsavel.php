<?php
session_start();
require_once '../config/conexao.php';

$erro = '';
$cpf_validado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'verificar_cpf';

    // ETAPA 1: Verificar se o CPF existe na tabela 'responsaveis'
    if ($acao === 'verificar_cpf') {
        $cpf_limpo = preg_replace('/\D/', '', $_POST['cpf'] ?? '');

        if (strlen($cpf_limpo) !== 11) {
            $erro = 'Digite um CPF válido com 11 números.';
        } else {
            $cpf_formatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf_limpo);

            $stmt = $conexao->prepare("SELECT id, cpf FROM responsaveis WHERE cpf = ? OR cpf = ? LIMIT 1");
            $stmt->bind_param("ss", $cpf_limpo, $cpf_formatado);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 1) {
                $dados = $res->fetch_assoc();
                $cpf_validado = $dados['cpf'];
            } else {
                $erro = 'CPF não encontrado em nosso cadastro de responsáveis.';
            }
            $stmt->close();
        }
    }

    // ETAPA 2: Salvar a nova senha no banco
    if ($acao === 'salvar_nova_senha') {
        $cpf_alvo       = $_POST['cpf_validado'] ?? '';
        $nova_senha     = $_POST['nova_senha'] ?? '';
        $confirma_senha = $_POST['confirma_senha'] ?? '';

        if (strlen($nova_senha) < 4) {
            $erro = 'A nova senha deve ter no mínimo 4 dígitos.';
            $cpf_validado = $cpf_alvo;
        } elseif ($nova_senha !== $confirma_senha) {
            $erro = 'As senhas não coincidem. Tente novamente.';
            $cpf_validado = $cpf_alvo;
        } else {
            $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);

            $stmtUp = $conexao->prepare("UPDATE responsaveis SET senha = ? WHERE cpf = ?");
            $stmtUp->bind_param("ss", $senhaHash, $cpf_alvo);

            if ($stmtUp->execute()) {
                echo "<script>
                    alert('Senha alterada com sucesso! Faça login com sua nova senha.');
                    window.location.href = 'login-responsavel.php';
                </script>";
                exit();
            } else {
                $erro = 'Erro ao atualizar a senha. Tente novamente.';
                $cpf_validado = $cpf_alvo;
            }
            $stmtUp->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EEP - Recuperar Senha (Responsável)</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #f4f7f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #1e293b;
        }

        .card-container {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            border-radius: 20px;
            padding: 36px 32px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .logo-box {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            border-radius: 16px;
            border: 2px solid #16803d;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1.25);
        }

        .title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
        }

        .subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
            margin-bottom: 24px;
        }

        .alert-erro {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            background-color: #ffffff;
        }

        input:focus {
            outline: none;
            border-color: #16803d;
            box-shadow: 0 0 0 3px rgba(22, 128, 61, 0.12);
        }

        .btn-primary {
            width: 100%;
            background-color: #16803d;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
        }

        .btn-primary:hover {
            background-color: #15803d;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            font-size: 13px;
            font-weight: 700;
            color: #16803d;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <div class="card-container">
        <div class="logo-box">
            <img src="../assets/img/logotipo.jpeg" alt="Logo EEP">
        </div>

        <h1 class="title">Recuperar Senha</h1>
        <p class="subtitle">
            <?= empty($cpf_validado) ? 'Informe seu CPF cadastrado para criar uma nova senha' : 'CPF confirmado! Digite sua nova senha abaixo' ?>
        </p>

        <?php if (!empty($erro)): ?>
            <div class="alert-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if (empty($cpf_validado)): ?>
            <!-- Etapa 1: Validar apenas o CPF -->
            <form method="POST">
                <input type="hidden" name="acao" value="verificar_cpf">
                <div class="form-group">
                    <label for="cpf">CPF Cadastrado</label>
                    <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" required>
                </div>
                <button type="submit" class="btn-primary">Verificar CPF</button>
            </form>
        <?php else: ?>
            <!-- Etapa 2: Definir Nova Senha -->
            <form method="POST">
                <input type="hidden" name="acao" value="salvar_nova_senha">
                <input type="hidden" name="cpf_validado" value="<?= htmlspecialchars($cpf_validado) ?>">
                <div class="form-group">
                    <label for="nova_senha">Nova Senha</label>
                    <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite a nova senha (mín. 4 dígitos)" minlength="4" required>
                </div>
                <div class="form-group">
                    <label for="confirma_senha">Confirme a Nova Senha</label>
                    <input type="password" id="confirma_senha" name="confirma_senha" placeholder="Repita a nova senha" minlength="4" required>
                </div>
                <button type="submit" class="btn-primary">Salvar Nova Senha</button>
            </form>
        <?php endif; ?>

        <a href="login-responsavel.php" class="back-link">← Voltar para o Login</a>
    </div>

    <script>
        const inputCpf = document.getElementById('cpf');
        if (inputCpf) {
            inputCpf.addEventListener('input', function(e) {
                let v = e.target.value.replace(/\D/g, '');
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = v;
            });
        }
    </script>
</body>
</html>
