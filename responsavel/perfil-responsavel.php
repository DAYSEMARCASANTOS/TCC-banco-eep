<?php
session_start();

if (!isset($_SESSION['responsavel_id'])) {
    header("Location: login-responsavel.php");
    exit();
}

$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "banco-eep";

$conn = new mysqli($host, $usuario, $senha, $banco);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$responsavel_id = $_SESSION['responsavel_id'];

// Busca dados completos do responsável
$stmtResp = $conn->prepare("SELECT nome_completo, cpf, telefone FROM responsaveis WHERE id = ?");
$stmtResp->bind_param("i", $responsavel_id);
$stmtResp->execute();
$dadosResp = $stmtResp->get_result()->fetch_assoc();
$stmtResp->close();

$nome_completo = $dadosResp['nome_completo'] ?? ($_SESSION['responsavel_nome'] ?? 'Responsável');
$cpf_raw       = preg_replace('/\D/', '', $dadosResp['cpf'] ?? '');
$cpf_formatado = strlen($cpf_raw) === 11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf_raw) : ($dadosResp['cpf'] ?? 'Não informado');
$telefone      = !empty($dadosResp['telefone']) ? $dadosResp['telefone'] : 'Não cadastrado';
$inicial       = strtoupper(substr(trim($nome_completo), 0, 1));

// Busca dependentes vinculados
$stmtFilhos = $conn->prepare("SELECT rf.nome_filho, e.ra FROM responsavel_filhos rf LEFT JOIN estudantes e ON REPLACE(REPLACE(e.cpf, '.', ''), '-', '') = rf.cpf_filho WHERE rf.responsavel_id = ?");
$stmtFilhos->bind_param("i", $responsavel_id);
$stmtFilhos->execute();
$resFilhos = $stmtFilhos->get_result();

$lista_filhos = [];
while ($f = $resFilhos->fetch_assoc()) {
    $lista_filhos[] = $f;
}
$stmtFilhos->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Banco EEP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body {
            background-color: #e9edf2;
            color: #111827;
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }
        .app-shell {
            width: 100%;
            max-width: 500px;
            background-color: #f4f6f9;
            min-height: 100vh;
            position: relative;
            padding-bottom: 85px;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.06);
        }

        /* Cabeçalho Verde (estilo Perfil do Estudante) */
        .profile-header {
            background: linear-gradient(135deg, #14532d 0%, #15803d 100%);
            color: #ffffff;
            padding: 24px 20px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .avatar-circle {
            width: 54px; height: 54px; border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.5);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: 800; color: #fff;
        }
        .header-info h2 { font-size: 16px; font-weight: 700; }
        .header-info p { font-size: 12px; color: #bbf7d0; margin-top: 3px; }
        .badge-role {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 5px 10px; border-radius: 8px;
            font-size: 11px; font-weight: 600;
        }

        /* Conteúdo e Cards */
        .content { padding: 16px; }
        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }
        .card-title {
            font-size: 14px; font-weight: 700; color: #111827;
            margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;
        }
        .info-group { margin-bottom: 12px; }
        .info-group:last-child { margin-bottom: 0; }
        .info-label { font-size: 10.5px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.4px; }
        .info-value { font-size: 14px; font-weight: 600; color: #1f2937; margin-top: 2px; }

        .btn-add-small {
            background: #f0fdf4; color: #15803d;
            border: 1px solid #bbf7d0; border-radius: 8px;
            padding: 5px 10px; font-size: 11.5px; font-weight: 700;
            text-decoration: none;
        }

        /* Segurança */
        .badge-status { background: #dcfce7; color: #15803d; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; }
        .sec-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .sec-text h4 { font-size: 13.5px; font-weight: 700; color: #111827; }
        .sec-text p { font-size: 11.5px; color: #6b7280; margin-top: 2px; }

        /* Switch Toggle */
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; inset: 0;
            background-color: #d1d5db; transition: .3s; border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px;
            left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%;
        }
        input:checked + .slider { background-color: #dc2626; }
        input:checked + .slider:before { transform: translateX(20px); }

        .btn-danger-full {
            width: 100%; padding: 12px; background: #dc2626; color: #fff;
            border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer;
        }

        /* Alterar Senha */
        .input-pass {
            width: 100%; padding: 11px 14px; border: 1px solid #d1d5db;
            border-radius: 10px; font-size: 13.5px; margin: 6px 0 12px; outline: none;
        }
        .input-pass:focus { border-color: #15803d; }
        .btn-dark-green {
            width: 100%; padding: 12px; background: #14532d; color: #fff;
            border: none; border-radius: 10px; font-size: 13.5px; font-weight: 700; cursor: pointer;
        }

        /* FAQ Acordeão */
        details { border: 1px solid #e5e7eb; border-radius: 10px; padding: 11px 14px; margin-bottom: 8px; }
        summary { font-size: 13px; font-weight: 600; color: #1f2937; cursor: pointer; list-style: none; display: flex; justify-content: space-between; }
        details p { font-size: 12.5px; color: #4b5563; margin-top: 8px; line-height: 1.45; }

        /* Botão Sair */
        .btn-logout {
            display: block; width: 100%; text-align: center;
            padding: 13px; background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 12px; color: #dc2626; font-size: 13.5px; font-weight: 700;
            text-decoration: none; margin-top: 6px;
        }

        /* Menu Inferior Fixo */
        .bottom-nav {
            position: fixed; bottom: 0; left: 50%; transform: translateX(-50%);
            width: 100%; max-width: 500px;
            background: #ffffff; border-top: 1px solid #e5e7eb;
            display: flex; justify-content: space-around;
            padding: 10px 0 12px; z-index: 90;
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 3px;
            text-decoration: none; color: #6b7280; font-size: 11px; font-weight: 600;
        }
        .nav-item.active { color: #15803d; }
        .nav-icon { font-size: 18px; }
    </style>
</head>
<body>

<div class="app-shell">
    <div class="profile-header">
        <div class="header-left">
            <div class="avatar-circle"><?php echo $inicial; ?></div>
            <div class="header-info">
                <h2><?php echo htmlspecialchars($nome_completo); ?></h2>
                <p>CPF: <?php echo htmlspecialchars($cpf_formatado); ?> • Banco EEP</p>
            </div>
        </div>
        <span class="badge-role">Responsável</span>
    </div>

    <div class="content">
        <!-- Informações da Conta -->
        <div class="card">
            <div class="card-title">
                <span>👤 Informações da Conta</span>
                <a href="painel-responsavel.php" class="btn-add-small">+ Vincular</a>
            </div>
            <div class="info-group">
                <div class="info-label">TELEFONE / WHATSAPP</div>
                <div class="info-value"><?php echo htmlspecialchars($telefone); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">DEPENDENTE(S) VINCULADO(S)</div>
                <div class="info-value">
                    <?php
                    if (empty($lista_filhos)) {
                        echo 'Nenhum dependente vinculado';
                    } else {
                        $nomes = array_map(fn($f) => $f['nome_filho'] . ' (RA: ' . ($f['ra'] ?? 'N/D') . ')', $lista_filhos);
                        echo htmlspecialchars(implode(', ', $nomes));
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Central de Segurança -->
        <div class="card">
            <div class="card-title">
                <span>🛡️ Central de Segurança Familiar</span>
                <span class="badge-status" id="status-cartao">Conta Ativa</span>
            </div>
            <div class="sec-row">
                <div class="sec-text">
                    <h4>Travar Compras dos Dependentes</h4>
                    <p>Bloqueia imediatamente gastos na cantina e papelaria.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" onchange="document.getElementById('status-cartao').textContent = this.checked ? 'Bloqueado' : 'Conta Ativa'; document.getElementById('status-cartao').style.background = this.checked ? '#fee2e2' : '#dcfce7'; document.getElementById('status-cartao').style.color = this.checked ? '#dc2626' : '#15803d';">
                    <span class="slider"></span>
                </label>
            </div>
            <button type="button" class="btn-danger-full" onclick="alert('Solicitação de bloqueio emergencial enviada à secretaria escolar!')">🚨 Bloquear Cartão Escolar (Perda ou Roubo)</button>
        </div>

        <!-- Alterar Senha -->
        <div class="card">
            <div class="card-title"><span>🔑 Alterar Senha de Acesso</span></div>
            <form action="../actions/acoes-responsavel.php" method="POST">
                <input type="hidden" name="acao" value="alterar_senha">
                <div class="info-label">NOVA SENHA</div>
                <input type="password" name="nova_senha" class="input-pass" placeholder="Digite a nova senha (mín. 4 dígitos)" minlength="4" required>
                <button type="submit" class="btn-dark-green">Salvar Nova Senha</button>
            </form>
        </div>

        <!-- Suporte e Ajuda -->
        <div class="card">
            <div class="card-title"><span>🎧 Suporte e Ajuda Banco EEP</span></div>
            <details>
                <summary>Como recarregar o saldo do meu filho? <span>⌄</span></summary>
                <p>Na tela inicial do Painel da Família, clique em "Recarregar" no cartão do dependente, escolha o valor e confirme.</p>
            </details>
            <details>
                <summary>Como funciona o limite diário de gastos? <span>⌄</span></summary>
                <p>Você define o valor máximo que o estudante pode gastar por dia na cantina. Ao atingir esse teto, novas compras são bloqueadas até o dia seguinte.</p>
            </details>
            <details>
                <summary>Horário de atendimento presencial na escola <span>⌄</span></summary>
                <p>Segunda a sexta-feira, das 07h30 às 17h00, na secretaria financeira da escola.</p>
            </details>
        </div>

        <a href="login-responsavel.php?sair=1" class="btn-logout">🚪 Sair da conta</a>
        </div>

    <nav class="bottom-nav">
        <a href="painel-responsavel.php" class="nav-item">
            <span class="nav-icon">🏠</span>
            <span>Início</span>
        </a>
        <a href="painel-responsavel.php" class="nav-item">
            <span class="nav-icon">💸</span>
            <span>Enviar</span>
        </a>
        <a href="painel-responsavel.php" class="nav-item">
            <span class="nav-icon">🛡️</span>
            <span>Controle</span>
        </a>
        <a href="painel-responsavel.php" class="nav-item">
            <span class="nav-icon">📄</span>
            <span>Extrato</span>
        </a>
        <a href="perfil-responsavel.php" class="nav-item active">
            <span class="nav-icon">👤</span>
            <span>Perfil</span>
        </a>
    </nav>
</div>

</body>
</html>
