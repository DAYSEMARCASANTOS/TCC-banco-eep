<?php
// Exemplo de estrutura para receber os dados do cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = htmlspecialchars($_POST['nome'] ?? '');
    $cpf   = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $cargo = htmlspecialchars($_POST['cargo'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';

    if ($senha !== $confirma_senha) {
        $erro = "As senhas não coincidem.";
    } else {
        // Criptografa a senha antes de salvar no banco
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Aqui entra seu INSERT no banco de dados (PDO / MySQLi)
        // header("Location: funcionario.php?cadastrado=1");
        // exit;
        $sucesso = "Cadastro realizado com sucesso!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Cadastro Funcionário</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        body {
            background-color: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo-box {
            width: 56px;
            height: 56px;
            border: 2px solid #7c3aed;
            border-radius: 12px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 12px;
            color: #1e3a8a;
            font-weight: bold;
            font-size: 24px;
        }
        .header h1 {
            font-size: 22px;
            color: #111827;
            margin-bottom: 4px;
        }
        .header p {
            font-size: 14px;
            color: #6b7280;
        }
        .back-link {
            display: inline-block;
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .profile-banner {
            background-color: #f5f3ff;
            border: 1px solid #ede9fe;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }
        .profile-badge {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            color: #7c3aed;
            font-weight: 700;
            font-size: 16px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .profile-info h2 {
            font-size: 15px;
            color: #111827;
        }
        .profile-info p {
            font-size: 13px;
            color: #6b7280;
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-success { background: #dcfce7; color: #166534; }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            background-color: #e8eff7;
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: 15px;
            color: #111827;
            outline: none;
        }
        .form-control:focus {
            border-color: #7c3aed;
            background-color: #ffffff;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background-color: #7c3aed;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn-submit:hover {
            background-color: #6d28d9;
        }
        .footer-links {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #4b5563;
        }
        .footer-links a {
            color: #7c3aed;
            font-weight: 600;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <div class="logo-box">P</div>
        <h1>Banco EEP</h1>
        <p>O banco do ecossistema escolar</p>
    </div>

    <a href="funcionario.php" class="back-link">← Voltar para o login</a>

    <div class="profile-banner">
        <div class="profile-badge">F</div>
        <div class="profile-info">
            <h2>Novo Funcionário</h2>
            <p>Crie sua conta para usar na cantina</p>
        </div>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-error"><?= $erro ?></div>
    <?php endif; ?>

    <?php if (!empty($sucesso)): ?>
        <div class="alert alert-success"><?= $sucesso ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="form-group">
            <label for="nome">Nome Completo</label>
            <input type="text" id="nome" name="nome" class="form-control" placeholder="Ex: Maria Silva" required>
        </div>

        <div class="form-group">
            <label for="cpf">CPF</label>
            <input type="text" id="cpf" name="cpf" class="form-control" placeholder="000.000.000-00" required>
        </div>

        <div class="form-group">
            <label for="cargo">Cargo / Setor</label>
            <select id="cargo" name="cargo" class="form-control" required>
                <option value="">Selecione seu cargo</option>
                <option value="professor">Professor(a)</option>
                <option value="administrativo">Administrativo</option>
                <option value="coordenacao">Coordenação / Direção</option>
                <option value="apoio">Equipe de Apoio</option>
            </select>
        </div>

        <div class="form-group">
            <label for="email">E-mail Institucional</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="nome@escola.edu.br" required>
        </div>

        <div class="form-group">
            <label for="senha">Crie uma Senha</label>
            <input type="password" id="senha" name="senha" class="form-control" placeholder="Mínimo 6 caracteres" minlength="6" required>
        </div>

        <div class="form-group">
            <label for="confirma_senha">Confirme a Senha</label>
            <input type="password" id="confirma_senha" name="confirma_senha" class="form-control" placeholder="Repita a senha" minlength="6" required>
        </div>

        <button type="submit" class="btn-submit">Finalizar Cadastro</button>
    </form>

    <div class="footer-links">
        Já possui cadastro? <a href="funcionario.php">Faça login aqui</a>
    </div>
</div>

</body>
</html>
