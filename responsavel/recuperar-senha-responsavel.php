<?php
// 1. Conexão com o banco de dados no XAMPP
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "banco-eep";

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro na conexão com o banco: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 2. Processamento das requisições vindas do formulário (via JavaScript)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $dados = json_decode(file_get_contents("php://input"), true);
    $acao = $dados['acao'] ?? '';

    // ETAPA 1 DO FLUXO: Verificar se o CPF existe
    if ($acao === 'verificar_cpf') {
        // Limpa pontos e traços para comparar apenas os 11 números
        $cpfLimpo = preg_replace('/\D/', '', $dados['cpf'] ?? '');

        if (strlen($cpfLimpo) !== 11) {
            echo json_encode(["status" => "erro", "mensagem" => "Digite um CPF válido com 11 números."]);
            exit;
        }

        // Busca primeiro na tabela 'estudantes'
        $stmt = $conn->prepare("SELECT id, nome_completo FROM estudantes WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?");
        $stmt->bind_param("s", $cpfLimpo);
        $stmt->execute();
        $resEstudante = $stmt->get_result();

        if ($user = $resEstudante->fetch_assoc()) {
            echo json_encode([
                "status" => "sucesso",
                "nome" => $user['nome_completo'],
                "tabela" => "estudantes",
                "perfil" => "Estudante"
            ]);
            exit;
        }
        $stmt->close();

        // Se não achou, busca na tabela 'responsaveis'
        $stmt = $conn->prepare("SELECT id, nome_completo FROM responsaveis WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?");
        $stmt->bind_param("s", $cpfLimpo);
        $stmt->execute();
        $resResp = $stmt->get_result();

        if ($user = $resResp->fetch_assoc()) {
            echo json_encode([
                "status" => "sucesso",
                "nome" => $user['nome_completo'],
                "tabela" => "responsaveis",
                "perfil" => "Responsável"
            ]);
            exit;
        }
        $stmt->close();

        // Se não encontrou em nenhuma das duas tabelas
        echo json_encode(["status" => "erro", "mensagem" => "CPF não encontrado no Banco EEP."]);
        exit;
    }
    // ETAPA 2 DO FLUXO: Salvar a nova senha criptografada no banco
    if ($acao === 'salvar_senha') {
        $cpfLimpo = preg_replace('/\D/', '', $dados['cpf'] ?? '');
        $tabela = $dados['tabela'] ?? '';
        $novaSenha = $dados['nova_senha'] ?? '';

        // Trava de segurança: impede alteração em qualquer outra tabela
        if ($tabela !== 'estudantes' && $tabela !== 'responsaveis') {
            echo json_encode(["status" => "erro", "mensagem" => "Perfil de usuário inválido."]);
            exit;
        }

        if (strlen($novaSenha) < 6) {
            echo json_encode(["status" => "erro", "mensagem" => "A senha deve ter no mínimo 6 caracteres."]);
            exit;
        }

        // Criptografa a senha exatamente como o seu login espera
        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

        // Atualiza a senha no MySQL
        $sql = "UPDATE `$tabela` SET senha = ? WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $senhaHash, $cpfLimpo);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(["status" => "sucesso", "mensagem" => "Senha alterada com sucesso!"]);
        } else {
            echo json_encode(["status" => "erro", "mensagem" => "Não foi possível atualizar a senha no banco."]);
        }
        
        $stmt->close();
        $conn->close();
        exit;
    }
} 
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Banco EEP</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #f3f4f6;
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
            padding: 40px 32px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        /* Ícone estilo Banco EEP */
        .logo-box {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border: 2.5px solid #15803d;
            border-radius: 14px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #1d4ed8;
        }

        h1 {
            font-size: 24px;
            color: #111827;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 24px;
            line-height: 1.4;
        }

        .badge-perfil {
            display: inline-block;
            background-color: #dcfce7;
            color: #15803d;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 16px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            font-size: 15px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background-color: #eef2ff;
            color: #1f2937;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            border-color: #15803d;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.15);
        }

        .btn-green {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            background-color: #15803d;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 8px;
        }

        .btn-green:hover {
            background-color: #166534;
        }

        .btn-green:disabled {
            background-color: #86efac;
            cursor: not-allowed;
        }

        .back-link {
            display: inline-block;
            margin-top: 24px;
            font-size: 14px;
            font-weight: 600;
            color: #15803d;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: none;
            text-align: left;
        }

        .alert-erro {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert-sucesso {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>

<div class="card">
    <!-- Ícone de cadeado -->
    <div class="logo-box">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="10" width="16" height="11" rx="2" ry="2"></rect>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
            <circle cx="12" cy="15" r="1"></circle>
        </svg>
    </div>

    <!-- Tag que mostrará "Perfil: Estudante" ou "Perfil: Responsável" -->
    <div id="perfil-tag" class="badge-perfil hidden"></div>

    <h1 id="page-title">Recuperar Senha</h1>
    <p class="subtitle" id="page-subtitle">Informe seu CPF cadastrado para criar uma nova senha</p>

    <!-- Caixa de mensagens de erro ou sucesso -->
    <div id="alert-box" class="alert"></div>

    <!-- FORMULÁRIO 1: Digitar CPF -->
    <form id="step-cpf">
        <div class="form-group">
            <label for="cpf">CPF Cadastrado</label>
            <input type="text" id="cpf" placeholder="000.000.000-00" maxlength="14" required>
        </div>
        <button type="submit" id="btn-verificar" class="btn-green">Verificar CPF</button>
    </form>

    <!-- FORMULÁRIO 2: Digitar Nova Senha (escondido inicialmente) -->
    <form id="step-password" class="hidden">
        <div class="form-group">
            <label for="new-password">Nova Senha</label>
            <input type="password" id="new-password" placeholder="Mínimo 6 caracteres" minlength="6" required>
        </div>
        <div class="form-group">
            <label for="confirm-password">Confirmar Nova Senha</label>
            <input type="password" id="confirm-password" placeholder="Repita a nova senha" minlength="6" required>
        </div>
        <button type="submit" id="btn-salvar" class="btn-green">Salvar Nova Senha</button>
    </form>

    <!-- Ajuste o href abaixo se o seu arquivo de login tiver outro nome (ex: index.php) -->
    <a href="login-responsavel.php" class="back-link">← Voltar para o Login</a>
</div>
<script>
    const inputCpf = document.getElementById('cpf');
    const formCpf = document.getElementById('step-cpf');
    const formPassword = document.getElementById('step-password');
    const alertBox = document.getElementById('alert-box');
    const pageTitle = document.getElementById('page-title');
    const pageSubtitle = document.getElementById('page-subtitle');
    const perfilTag = document.getElementById('perfil-tag');

    // Guarda temporariamente o CPF e a tabela (estudantes ou responsaveis) encontrados
    let dadosUsuario = { cpf: '', tabela: '' };

    // 1. Máscara automática de CPF (000.000.000-00)
    inputCpf.addEventListener('input', function(e) {
        let v = e.target.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = v;
    });

    function mostrarAlerta(mensagem, tipo) {
        alertBox.textContent = mensagem;
        alertBox.className = `alert alert-${tipo}`;
        alertBox.style.display = 'block';
    }

    // 2. Envio da Etapa 1: Verificar CPF no MySQL
    formCpf.addEventListener('submit', async function(e) {
        e.preventDefault();
        alertBox.style.display = 'none';
        
        const btn = document.getElementById('btn-verificar');
        btn.disabled = true;
        btn.textContent = 'Verificando...';

        try {
            const res = await fetch('recuperar-senha.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    acao: 'verificar_cpf',
                    cpf: inputCpf.value
                })
            });
            const data = await res.json();

            if (data.status === 'sucesso') {
                dadosUsuario.cpf = inputCpf.value;
                dadosUsuario.tabela = data.tabela;

                // Mostra a tag verde com o perfil encontrado
                perfilTag.textContent = `Perfil: ${data.perfil}`;
                perfilTag.classList.remove('hidden');

                // Troca o formulário de CPF pelo formulário de Nova Senha
                formCpf.classList.add('hidden');
                formPassword.classList.remove('hidden');
                pageTitle.textContent = 'Criar Nova Senha';
                pageSubtitle.textContent = `Olá, ${data.nome}. Digite sua nova senha abaixo.`;
            } else {
                mostrarAlerta(data.mensagem, 'erro');
            }
        } catch (err) {
            mostrarAlerta('Erro ao conectar com o servidor XAMPP. Verifique se o Apache está ativo.', 'erro');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Verificar CPF';
        }
    });

    // 3. Envio da Etapa 2: Salvar a nova senha criptografada
    formPassword.addEventListener('submit', async function(e) {
        e.preventDefault();
        alertBox.style.display = 'none';

        const novaSenha = document.getElementById('new-password').value;
        const confirmarSenha = document.getElementById('confirm-password').value;

        if (novaSenha !== confirmarSenha) {
            mostrarAlerta('As senhas não coincidem. Digite novamente.', 'erro');
            return;
        }

        const btn = document.getElementById('btn-salvar');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
            const res = await fetch('recuperar-senha.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    acao: 'salvar_senha',
                    cpf: dadosUsuario.cpf,
                    tabela: dadosUsuario.tabela,
                    nova_senha: novaSenha
                })
            });
            const data = await res.json();

            if (data.status === 'sucesso') {
                formPassword.classList.add('hidden');
                pageTitle.textContent = 'Senha Alterada!';
                pageSubtitle.textContent = 'Sua nova senha já está salva no Banco EEP.';
                mostrarAlerta(data.mensagem, 'sucesso');
            } else {
                mostrarAlerta(data.mensagem, 'erro');
            }
        } catch (err) {
            mostrarAlerta('Erro ao salvar a nova senha no banco de dados.', 'erro');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar Nova Senha';
        }
    });
</script>

</body>
</html>


