<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Login Funcionário</title>
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
            border: 2px solid #7c3aed; /* Roxo do perfil Funcionários */
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
        .back-link:hover {
            color: #111827;
        }
        .profile-banner {
            background-color: #f5f3ff; /* Fundo roxo claro */
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .profile-info h2 {
            font-size: 15px;
            color: #111827;
            margin-bottom: 2px;
        }
        .profile-info p {
            font-size: 13px;
            color: #6b7280;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }
        .input-wrapper {
            position: relative;
        }
        .form-control {
            width: 100%;
            padding: 13px 16px;
            background-color: #e8eff7;
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: 15px;
            color: #111827;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: #7c3aed;
            background-color: #ffffff;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #6b7280;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background-color: #7c3aed; /* Botão roxo */
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: background-color 0.2s;
        }
        .btn-submit:hover {
            background-color: #6d28d9;
        }
        .footer-links {
            text-align: center;
            margin-top: 22px;
            font-size: 14px;
            color: #4b5563;
            line-height: 1.8;
        }
        .footer-links a {
            color: #7c3aed;
            font-weight: 600;
            text-decoration: none;
        }
        .footer-links a:hover {
            text-decoration: underline;
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

    <a href="../login.php" class="back-link">← Trocar perfil</a>

    <div class="profile-banner">
        <div class="profile-badge">F</div>
        <div class="profile-info">
            <h2>Funcionários</h2>
            <p>Consulte saldo e faça pagamentos</p>
        </div>
    </div>

    <form action="processa_login_funcionario.php" method="POST">
        <div class="form-group">
            <label for="cpf">CPF do Funcionário</label>
            <input type="text" id="cpf" name="cpf" class="form-control" placeholder="Digite seu CPF" required>
        </div>

        <div class="form-group">
            <label for="senha">Senha</label>
            <div class="input-wrapper">
                <input type="password" id="senha" name="senha" class="form-control" placeholder="••••••" required>
                <button type="button" class="toggle-password" onclick="mostrarSenha()">👁️</button>
            </div>
        </div>

        <button type="submit" class="btn-submit">Entrar</button>
    </form>

    <div class="footer-links">
        <div>Não tem cadastro ainda? <a href="cadastro_funcionario.php">Faça cadastro aqui</a></div>
        <div>Esqueceu senha? <a href="recuperar_senha.php">Recupere Aqui</a></div>
    </div>
</div>

<script>
    function mostrarSenha() {
        const input = document.getElementById('senha');
        input.type = input.type === 'password' ? 'text' : 'password';
    }
</script>

</body>
</html>
