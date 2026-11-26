<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Login Estudante</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #1e293b;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Moldura da imagem do logotipo */
        .logo-box {
            background-color: #ffffff;
            width: 72px;
            height: 72px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 16px;
            border: 2px solid #3b82f6;
            box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.2);
            margin-bottom: 16px;
            overflow: hidden;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transform: scale(1.25);
        }

        .brand-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .brand-subtitle {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 24px;
        }

        .trocar-perfil {
            align-self: flex-start;
            font-size: 14px;
            color: #64748b;
            text-decoration: none;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .trocar-perfil:hover {
            color: #1e3a8a;
        }

        .profile-card {
            width: 100%;
            background-color: #f0f6ff;
            border: 2px solid #dbeafe;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .profile-icon {
            font-size: 24px;
            font-weight: 700;
            color: #1e3a8a;
            background: #fff;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .profile-info h3 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .profile-info p {
            font-size: 13px;
            color: #64748b;
        }

        form {
            width: 100%;
        }

        .form-group {
            width: 100%;
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            font-size: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            outline: none;
            transition: all 0.2s;
            background-color: #fff;
        }

        .form-group input:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .btn-entrar {
            width: 100%;
            background-color: #1e3a8a;
            color: white;
            border: none;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
            box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.2);
        }

        .btn-entrar:hover {
            background-color: #172554;
        }

        .register-footer {
            margin-top: 24px;
            font-size: 13px;
            color: #64748b;
            text-align: center;
        }

        .register-footer a {
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 600;
        }

        .register-footer a:hover {
            text-decoration: underline;
        }

        .password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.password-wrapper input {
    width: 100%;
    padding-right: 42px; /* Espaço para o ícone não cobrir o texto */
}
.toggle-password {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: #64748b;
    padding: 4px;
}

    </style>
</head>
<body>

    <div class="login-container">
        <!-- Logotipo -->
        <div class="logo-box">
        <img src="../assets/img/logotipo.jpeg" alt="Logo EEP" class="w-full h-full object-contain scale-90">

    </div>
        
        <h1 class="brand-title">Banco EEP</h1>
        <p class="brand-subtitle">O banco do ecossistema escolar</p>

        <!-- Voltar / Trocar Perfil -->
        <a href="../login.php" class="trocar-perfil">← Trocar perfil</a>

        <!-- Card Perfil Estudante Selecionado -->
        <div class="profile-card">
            <div class="profile-icon">E</div>
            <div class="profile-info">
                <h3>Estudante</h3>
                <p>Consulte saldo e faça pagamentos</p>
            </div>
        </div>

        <!-- Formulário conectado ao processar-login.php -->
        <form action="../actions/processar-login.php" method="POST">
    <div class="form-group">
        <label for="matricula">RA (Matrícula)</label>
        <input type="text" id="matricula" name="matricula" placeholder="Digite seu RA" required>
    </div>
    
    <div class="form-group">
    <label for="senha">Senha</label>
    <div class="password-wrapper">
        <input type="password" id="senha" name="senha" placeholder="Sua senha" required>
        <button type="button" class="toggle-password" onclick="alternarSenha()" title="Mostrar ou ocultar senha">👁️</button>
    </div>
    </div>


    <button type="submit" class="btn-entrar">Entrar</button>
    <div class="register-footer">
         Já fez seu cadastro? <a href="cadastro-estudante.php">Faça cadastro aqui</a>
     </div>
    <div class="register-footer">
         Esqueceu senha? <a href="recuperar-senha.php">Recupere Aqui</a>
    </div>

</form>

    <script>
function alternarSenha() {
    const campo = document.getElementById('senha');
    const botao = document.querySelector('.toggle-password');
    if (campo.type === 'password') {
        campo.type = 'text';
        botao.innerText = '🙈';
    } else {
        campo.type = 'password';
        botao.innerText = '👁️';
    }
}
</script>


</body>
</html>
