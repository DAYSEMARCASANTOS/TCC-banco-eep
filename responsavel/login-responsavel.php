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

// Garante que a tabela de responsáveis exista no banco
$conexao->query("CREATE TABLE IF NOT EXISTS responsaveis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(120) NOT NULL,
    cpf VARCHAR(14) NOT NULL UNIQUE,
    ra_estudante VARCHAR(20) NOT NULL,
    senha VARCHAR(100) NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cpf   = trim($_POST['cpf'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    $stmt = $conexao->prepare("SELECT id, nome_completo, cpf, ra_estudante, senha FROM responsaveis WHERE cpf = ?");
    $stmt->bind_param("s", $cpf);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $resp = $res->fetch_assoc();
        if ($senha === $resp['senha'] || password_verify($senha, $resp['senha'])) {
            $_SESSION['responsavel_id']   = $resp['id'];
            $_SESSION['responsavel_nome'] = $resp['nome_completo'];
            $_SESSION['ra_estudante']     = $resp['ra_estudante'];
            header("Location: painel-responsavel.php");
            exit;
        } else {
            $erro = "Senha incorreta. Tente novamente.";
        }
    } else {
        $erro = "CPF de responsável não encontrado. Faça seu cadastro abaixo.";
    }
    $stmt->close();
}

$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Login Responsável</title>
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #1e293b; padding: 20px; }
        .login-container { width: 100%; max-width: 420px; padding: 30px; background: #fff; border-radius: 18px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }

        .logo-box {
            width: 68px; height: 68px; margin: 0 auto 12px auto; border-radius: 16px;
            border: 2px solid #16a34a; display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .logo-box img { width: 100%; height: 100%; object-fit: contain; transform: scale(1.3); }
        .brand-title { font-size: 22px; font-weight: 800; color: #0f172a; text-align: center; }
        .brand-subtitle { font-size: 13px; color: #64748b; text-align: center; margin-top: 2px; margin-bottom: 22px; }

        .back-link { display: inline-block; font-size: 13px; color: #64748b; text-decoration: none; margin-bottom: 12px; font-weight: 500; }
        .back-link:hover { color: #0f172a; }

        /* Cartão de Perfil Selecionado (Verde Responsável) */
        .profile-badge-card {
            display: flex; align-items: center; gap: 14px; background: #f0fdf4;
            border: 1px solid #bbf7d0; border-radius: 14px; padding: 14px; margin-bottom: 22px;
        }
        .profile-letter {
            width: 44px; height: 44px; background: #ffffff; color: #15803d;
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 800; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0;
        }
        .profile-info-title { font-size: 14px; font-weight: 700; color: #0f172a; }
        .profile-info-desc { font-size: 12px; color: #64748b; margin-top: 2px; }

        .alert-error {
            background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;
            padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 16px;
        }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-group input {
            width: 100%; padding: 13px 14px; font-size: 14px; border: 1px solid #cbd5e1;
            border-radius: 10px; outline: none; background-color: #f1f5f9; transition: 0.2s;
        }
        .form-group input:focus { border-color: #16a34a; background-color: #fff; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12); }

        .password-wrapper { position: relative; display: flex; align-items: center; }
        .password-wrapper input { padding-right: 42px; }
        .toggle-password {
            position: absolute; right: 12px; background: none; border: none;
            cursor: pointer; font-size: 16px; color: #64748b; padding: 4px;
        }

        .btn-entrar {
            width: 100%; background-color: #15803d; color: white; border: none;
            padding: 14px; font-size: 15px; font-weight: 600; border-radius: 10px;
            cursor: pointer; transition: 0.2s; margin-top: 4px;
        }
        .btn-entrar:hover { background-color: #166534; }

        .footer-links { margin-top: 20px; text-align: center; font-size: 13px; color: #64748b; display: flex; flex-direction: column; gap: 10px; }
        .footer-links a { color: #15803d; text-decoration: none; font-weight: 600; }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-box">
            <img src="../assets/img/logotipo.jpeg" alt="Logo EEP">
        </div>
        <h1 class="brand-title">Banco EEP</h1>
        <p class="brand-subtitle">O banco do ecossistema escolar</p>

        <a href="../login.php" class="back-link">← Trocar perfil</a>

        <div class="profile-badge-card">
            <div class="profile-letter">R</div>
            <div>
                <div class="profile-info-title">Responsável</div>
                <div class="profile-info-desc">Monitore e controle os gastos</div>
            </div>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="alert-error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="cpf">CPF do Responsável</label>
                <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" required>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <div class="password-wrapper">
                    <input type="password" id="senha" name="senha" placeholder="Sua senha" required>
                    <button type="button" class="toggle-password" onclick="alternarSenha()">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-entrar">Entrar</button>
        </form>

        <div class="footer-links">
            <div>Não tem cadastro ainda? <a href="cadastro-responsavel.php">Faça cadastro aqui</a></div>
            <div>Esqueceu senha? <a href="recuperar-senha-responsavel.php">Recupere Aqui</a></div>

        </div>
    </div>

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

        document.getElementById('cpf').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = v;
        });
    </script>
</body>
</html>
