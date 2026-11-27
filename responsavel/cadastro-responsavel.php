<?php
// 1. Conexão com o banco-eep no XAMPP
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "banco-eep";

$conn = new mysqli($host, $usuario, $senha, $banco);
if ($conn->connect_error) {
    die(json_encode(["status" => "erro", "mensagem" => "Erro na conexão com o banco: " . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");

// 2. Recebimento e Validação dos Dados (via POST do JavaScript)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $dados = json_decode(file_get_contents("php://input"), true);

    $nome = trim($dados['nome'] ?? '');
    $cpf = preg_replace('/\D/', '', $dados['cpf'] ?? ''); // Salva apenas números (11 dígitos)
    $telefone = trim($dados['telefone'] ?? '');
    $senhaPura = $dados['senha'] ?? '';
    $filhos = $dados['filhos'] ?? [];

    if (empty($nome) || strlen($cpf) !== 11 || empty($senhaPura) || empty($filhos)) {
        echo json_encode(["status" => "erro", "mensagem" => "Preencha todos os campos obrigatórios e um CPF válido."]);
        exit;
    }

    // Verifica se o CPF do responsável já está cadastrado
    $stmtCheck = $conn->prepare("SELECT id FROM responsaveis WHERE cpf = ?");
    $stmtCheck->bind_param("s", $cpf);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        echo json_encode(["status" => "erro", "mensagem" => "Este CPF já possui cadastro como responsável."]);
        exit;
    }
    $stmtCheck->close();

    // Verifica se TODOS os RAs informados existem na tabela 'estudantes' e pega os dados reais deles
    $primeiroRa = '';
    $filhosValidados = [];

    foreach ($filhos as $index => $filho) {
        $ra = trim($filho['ra'] ?? '');
        if ($index === 0) {
            $primeiroRa = $ra;
        }

        $stmtAluno = $conn->prepare("SELECT id, nome_completo, cpf FROM estudantes WHERE ra = ?");
        $stmtAluno->bind_param("s", $ra);
        $stmtAluno->execute();
        $resAluno = $stmtAluno->get_result();

        if ($dadosAluno = $resAluno->fetch_assoc()) {
            $filhosValidados[] = [
                'ra'   => $ra,
                'nome' => $dadosAluno['nome_completo'],
                'cpf'  => preg_replace('/\D/', '', $dadosAluno['cpf'])
            ];
        } else {
            echo json_encode(["status" => "erro", "mensagem" => "Estudante com RA ($ra) não encontrado no Banco EEP."]);
            exit;
        }
        $stmtAluno->close();
    }

    // 3. Inicia a transação para salvar e vincular tudo com segurança
    $conn->begin_transaction();

    try {
        // A. Cria a senha criptografada com password_hash
        $senhaHash = password_hash($senhaPura, PASSWORD_DEFAULT);

        // B. Insere o Responsável na tabela 'responsaveis'
        $stmtResp = $conn->prepare("INSERT INTO responsaveis (nome_completo, cpf, telefone, ra_estudante, senha) VALUES (?, ?, ?, ?, ?)");
        $stmtResp->bind_param("sssss", $nome, $cpf, $telefone, $primeiroRa, $senhaHash);
        $stmtResp->execute();
        $responsavelId = $conn->insert_id;
        $stmtResp->close();

        // C. Vincula cada filho na tabela 'responsavel_filhos' usando as colunas exatas do seu banco
        foreach ($filhosValidados as $filho) {
            $stmtVinc = $conn->prepare("INSERT INTO responsavel_filhos (responsavel_id, nome_filho, cpf_filho) VALUES (?, ?, ?)");
            $stmtVinc->bind_param("iss", $responsavelId, $filho['nome'], $filho['cpf']);
            $stmtVinc->execute();
            $stmtVinc->close();
        }

        $conn->commit();
        echo json_encode(["status" => "sucesso", "mensagem" => "Conta criada e vinculada ao estudante com sucesso!"]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "erro", "mensagem" => "Erro no banco: " . $e->getMessage()]);
    }

    $conn->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Responsável - Banco EEP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 24px 16px; }
        .card { background: #ffffff; width: 100%; max-width: 460px; padding: 36px 32px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); }
        .header { text-align: center; margin-bottom: 24px; }
        .logo-box {
            width: 64px;
            height: 64px;
            margin: 0 auto 12px;
            border: 2.5px solid #15803d;
            border-radius: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #ffffff;
            overflow: hidden;
            padding: 4px;
}

         .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
}

        .header h1 { font-size: 22px; color: #111827; font-weight: 800; }
        .header p { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        input { width: 100%; padding: 12px 14px; font-size: 14px; border: 1px solid #d1d5db; border-radius: 10px; background-color: #f9fafb; color: #1f2937; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
        input:focus { border-color: #15803d; background-color: #ffffff; box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.15); }
        .dependents-box { background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; margin: 20px 0; }
        .dependents-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .dependents-header span { font-size: 13px; font-weight: 600; color: #166534; }
        .badge-count { background-color: #15803d; color: #ffffff; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; }
        .filho-item { background: #ffffff; border: 1px solid #dcfce7; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
        .filho-title { font-size: 11px; font-weight: 700; color: #15803d; text-transform: uppercase; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
        .btn-remove { color: #dc2626; background: none; border: none; font-size: 12px; cursor: pointer; font-weight: 600; }
        .filho-item input { margin-bottom: 8px; background-color: #eef2ff; }
        .filho-item input:last-child { margin-bottom: 0; }
        .btn-add-filho { width: 100%; padding: 10px; background: #ffffff; border: 1.5px dashed #86efac; border-radius: 8px; color: #15803d; font-size: 13px; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
        .btn-add-filho:hover { background-color: #dcfce7; }
        .btn-submit { width: 100%; padding: 14px; font-size: 15px; font-weight: 600; color: #ffffff; background-color: #15803d; border: none; border-radius: 10px; cursor: pointer; transition: background-color 0.2s; margin-top: 8px; }
        .btn-submit:hover { background-color: #166534; }
        .btn-submit:disabled { background-color: #86efac; cursor: not-allowed; }
        .footer-link { text-align: center; margin-top: 18px; font-size: 13px; color: #6b7280; }
        .footer-link a { color: #15803d; font-weight: 600; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }
        .alert { padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; display: none; }
        .alert-erro { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-sucesso { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>
<div class="card">
<div class="card">
    <div class="header">
        <div class="logo-box">
            <img src="../assets/img/logotipo.jpeg" alt="Logotipo Banco EEP">
        </div>
        <h1>Banco EEP</h1>
        <p>O banco do ecossistema escolar</p>
    </div>


    <div id="alert-box" class="alert"></div>

    <form id="form-cadastro">
        <div class="form-group">
            <label for="nome">Seu Nome Completo</label>
            <input type="text" id="nome" placeholder="Digite seu nome completo" required>
        </div>

        <div class="form-group">
            <label for="cpf">Seu CPF</label>
            <input type="text" id="cpf" placeholder="000.000.000-00" maxlength="14" required>
        </div>

        <div class="form-group">
            <label for="telefone">Telefone / WhatsApp</label>
            <input type="text" id="telefone" placeholder="(11) 99999-9999" maxlength="15" required>
        </div>
        <div class="dependents-box">
            <div class="dependents-header">
                <span>Dependentes / Filhos Vinculados</span>
                <span class="badge-count" id="contador-filhos">1 filho</span>
            </div>

            <div id="lista-filhos">
                <div class="filho-item">
                    <div class="filho-title"><span>FILHO(A) #1</span></div>
                    <input type="text" class="filho-nome" placeholder="Nome completo do filho(a)" required>
                    <input type="text" class="filho-ra" placeholder="Matrícula (RA) do estudante" required>
                </div>
            </div>

            <button type="button" class="btn-add-filho" id="btn-add-filho">+ Adicionar outro filho</button>
        </div>

        <div class="form-group">
            <label for="senha">Crie uma Senha</label>
            <input type="password" id="senha" placeholder="Mínimo 6 caracteres" minlength="6" required>
        </div>

        <div class="form-group">
            <label for="confirmar-senha">Confirme sua Senha</label>
            <input type="password" id="confirmar-senha" placeholder="Repita a senha" minlength="6" required>
        </div>

        <button type="submit" id="btn-submit" class="btn-submit">Criar Conta de Responsável</button>
    </form>

    <div class="footer-link">
        Já tem uma conta? <a href="login-responsavel.php">Faça login aqui</a>
    </div>
</div>

<script>
    document.getElementById('cpf').addEventListener('input', function(e) {
        let v = e.target.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = v;
    });

    document.getElementById('telefone').addEventListener('input', function(e) {
        let v = e.target.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/^(\d{2})(\d)/g, '($1) $2');
        v = v.replace(/(\d)(\d{4})$/, '$1-$2');
        e.target.value = v;
    });

    const listaFilhos = document.getElementById('lista-filhos');
    const contadorFilhos = document.getElementById('contador-filhos');

    function atualizarContagem() {
        const itens = listaFilhos.querySelectorAll('.filho-item');
        contadorFilhos.textContent = `${itens.length} ${itens.length === 1 ? 'filho' : 'filhos'}`;
        itens.forEach((item, index) => {
            const titulo = item.querySelector('.filho-title span');
            titulo.textContent = `FILHO(A) #${index + 1}`;
        });
    }

    document.getElementById('btn-add-filho').addEventListener('click', function() {
        const div = document.createElement('div');
        div.className = 'filho-item';
        div.innerHTML = `
            <div class="filho-title">
                <span>FILHO(A)</span>
                <button type="button" class="btn-remove" onclick="this.closest('.filho-item').remove(); atualizarContagem();">Remover</button>
            </div>
            <input type="text" class="filho-nome" placeholder="Nome completo do filho(a)" required>
            <input type="text" class="filho-ra" placeholder="Matrícula (RA) do estudante" required>
        `;
        listaFilhos.appendChild(div);
        atualizarContagem();
    });

    document.getElementById('form-cadastro').addEventListener('submit', async function(e) {
        e.preventDefault();

        const alertBox = document.getElementById('alert-box');
        alertBox.style.display = 'none';

        const senha = document.getElementById('senha').value;
        const confirmar = document.getElementById('confirmar-senha').value;

        if (senha !== confirmar) {
            alertBox.textContent = 'As senhas não coincidem. Digite novamente.';
            alertBox.className = 'alert alert-erro';
            alertBox.style.display = 'block';
            return;
        }

        const filhos = [];
        document.querySelectorAll('.filho-item').forEach(item => {
            filhos.push({
                nome: item.querySelector('.filho-nome').value,
                ra: item.querySelector('.filho-ra').value
            });
        });

        const btn = document.getElementById('btn-submit');
        btn.disabled = true;
        btn.textContent = 'Criando conta...';

        try {
            const res = await fetch('cadastro-responsavel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nome: document.getElementById('nome').value,
                    cpf: document.getElementById('cpf').value,
                    telefone: document.getElementById('telefone').value,
                    senha: senha,
                    filhos: filhos
                })
            });

            const data = await res.json();

            alertBox.textContent = data.mensagem;
            alertBox.className = `alert alert-${data.status}`;
            alertBox.style.display = 'block';

            if (data.status === 'sucesso') {
                document.getElementById('form-cadastro').reset();
                setTimeout(() => {
                    window.location.href = 'login-responsavel.php';
                }, 1500);
            }
        } catch (err) {
            alertBox.textContent = 'Erro de conexão com o XAMPP. Verifique se o Apache e o MySQL estão ativos.';
            alertBox.className = 'alert alert-erro';
            alertBox.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Criar Conta de Responsável';
        }
    });
</script>
</body>
</html>


