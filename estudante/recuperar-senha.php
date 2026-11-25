<?php
$servidor = "localhost";
$usuario  = "root";
$senha_db = "";
$banco    = "banco-eep";

$conexao = new mysqli($servidor, $usuario, $senha_db, $banco);
if ($conexao->connect_error) {
    die("Falha na conexão: " . $conexao->connect_error);
}
$conexao->set_charset("utf8mb4");

$mensagem = "";
$tipo_msg = "";
$etapa = 1; // Etapa 1: Validar RA + CPF | Etapa 2: Criar nova senha
$ra_validado = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST['acao'] ?? '';

    // Etapa 1: Confere se RA e CPF pertencem ao mesmo estudante
    if ($acao === 'validar_dados') {
        $ra  = trim($_POST['ra'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');

        $stmt = $conexao->prepare("SELECT id, nome_completo FROM estudantes WHERE ra = ? AND cpf = ?");
        $stmt->bind_param("ss", $ra, $cpf);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $aluno = $res->fetch_assoc();
            $etapa = 2;
            $ra_validado = $ra;
            $mensagem = "✔ Identidade confirmada, " . htmlspecialchars($aluno['nome_completo']) . "! Crie sua nova senha abaixo.";
            $tipo_msg = "success";
        } else {
            $mensagem = "❌ RA ou CPF não conferem. Verifique os dados digitados e tente novamente.";
            $tipo_msg = "error";
        }
        $stmt->close();
    }

    // Etapa 2: Atualiza a nova senha no banco
    if ($acao === 'salvar_nova_senha') {
        $ra         = trim($_POST['ra'] ?? '');
        $nova_senha = trim($_POST['nova_senha'] ?? '');
        $confirma   = trim($_POST['confirma_senha'] ?? '');

        if (strlen($nova_senha) < 4) {
            $etapa = 2;
            $ra_validado = $ra;
            $mensagem = "⚠ A nova senha precisa ter pelo menos 4 caracteres.";
            $tipo_msg = "error";
        } elseif ($nova_senha !== $confirma) {
            $etapa = 2;
            $ra_validado = $ra;
            $mensagem = "⚠ As duas senhas digitadas não são iguais.";
            $tipo_msg = "error";
        } else {
            $stmt = $conexao->prepare("UPDATE estudantes SET senha = ? WHERE ra = ?");
            $stmt->bind_param("ss", $nova_senha, $ra);
            if ($stmt->execute()) {
                echo "<script>alert('Senha redefinida com sucesso! Faça login com sua nova senha.'); window.location.href='login-estudante.php';</script>";
                exit;
            }
            $stmt->close();
        }
    }
}
$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Recuperar Senha</title>
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #1e293b; padding: 20px; }
        .card-container { width: 100%; max-width: 430px; padding: 30px; background: #fff; border-radius: 18px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        
        .logo-box {
            width: 64px; height: 64px; margin: 0 auto 12px auto; border-radius: 16px;
            border: 2px solid #3b82f6; display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .logo-box img { width: 100%; height: 100%; object-fit: contain; transform: scale(1.3); }
        .title { font-size: 20px; font-weight: 800; color: #0f172a; text-align: center; }
        .subtitle { font-size: 13px; color: #64748b; text-align: center; margin: 4px 0 22px 0; }

        .alert-box { padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 12px 14px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 10px; outline: none; background: #f8fafc; transition: 0.2s; }
        .form-group input:focus { border-color: #1e3a8a; background: #fff; }

        .btn-primary { width: 100%; background-color: #1e3a8a; color: white; border: none; padding: 14px; font-size: 15px; font-weight: 600; border-radius: 10px; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background-color: #172554; }

        .back-link { display: block; text-align: center; margin-top: 18px; font-size: 13px; color: #1e3a8a; text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card-container">
        <div class="logo-box">
            <img src="../assets/img/logotipo.jpeg" alt="Logo EEP">
        </div>
        <h1 class="title">Recuperar Senha</h1>
        <p class="subtitle">Confirme seus dados escolares para criar uma nova senha</p>

        <?php if (!empty($mensagem)): ?>
            <div class="alert-box <?= $tipo_msg === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <?php if ($etapa === 1): ?>
            <!-- Etapa 1: Validar RA e CPF -->
            <form method="POST">
                <input type="hidden" name="acao" value="validar_dados">
                <div class="form-group">
                    <label for="ra">RA (Matrícula)</label>
                    <input type="text" id="ra" name="ra" placeholder="Digite seu RA de 10 dígitos" maxlength="10" required>
                </div>
                <div class="form-group">
                    <label for="cpf">CPF Cadastrado</label>
                    <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" required>
                </div>
                <button type="submit" class="btn-primary">Verificar Dados</button>
            </form>
        <?php else: ?>
            <!-- Etapa 2: Definir Nova Senha -->
            <form method="POST">
                <input type="hidden" name="acao" value="salvar_nova_senha">
                <input type="hidden" name="ra" value="<?= htmlspecialchars($ra_validado) ?>">
                <div class="form-group">
                    <label for="nova_senha">Nova Senha</label>
                    <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite a nova senha (mín. 4 dígitos)" required>
                </div>
                <div class="form-group">
                    <label for="confirma_senha">Confirme a Nova Senha</label>
                    <input type="password" id="confirma_senha" name="confirma_senha" placeholder="Repita a nova senha" required>
                </div>
                <button type="submit" class="btn-primary">Salvar Nova Senha</button>
            </form>
        <?php endif; ?>

        <a href="login-estudante.php" class="back-link">← Voltar para o Login</a>
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
