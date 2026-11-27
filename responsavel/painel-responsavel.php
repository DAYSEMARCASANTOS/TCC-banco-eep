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

$responsavel_id   = $_SESSION['responsavel_id'];
$responsavel_nome = $_SESSION['responsavel_nome'] ?? 'Responsável';
$primeiro_nome = strtok(trim($responsavel_nome), ' ');



$sql = "SELECT 
            rf.id AS vinculo_id,
            rf.nome_filho,
            rf.cpf_filho,
            e.*
        FROM responsavel_filhos rf
        LEFT JOIN estudantes e ON REPLACE(REPLACE(e.cpf, '.', ''), '-', '') = rf.cpf_filho
        WHERE rf.responsavel_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $responsavel_id);
$stmt->execute();
$resultado = $stmt->get_result();

$dependentes = [];
$extratos_por_ra = [];

while ($row = $resultado->fetch_assoc()) {
    $ra_aluno = $row['ra'] ?? '';
    $gasto_hoje = 0.0;

    if (!empty($ra_aluno)) {
        $stmtGasto = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total_hoje FROM transacoes WHERE ra = ? AND tipo = 'saida' AND DATE(data_hora) = CURDATE()");
        $stmtGasto->bind_param("s", $ra_aluno);
        $stmtGasto->execute();
        $gasto_hoje = (float) ($stmtGasto->get_result()->fetch_assoc()['total_hoje'] ?? 0);
        $stmtGasto->close();

        $stmtTrans = $conn->prepare("SELECT descricao, categoria, valor, tipo, data_hora FROM transacoes WHERE ra = ? ORDER BY data_hora DESC LIMIT 20");
        $stmtTrans->bind_param("s", $ra_aluno);
        $stmtTrans->execute();
        $resTrans = $stmtTrans->get_result();
        $lista_trans = [];
        while ($t = $resTrans->fetch_assoc()) {
            $lista_trans[] = $t;
        }
        $extratos_por_ra[$ra_aluno] = $lista_trans;
        $stmtTrans->close();
    }

    $row['gasto_hoje'] = $gasto_hoje;
    $dependentes[] = $row;
}
$stmt->close();

$total_dependentes = count($dependentes);
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel da Família - Banco EEP</title>
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

        /* Cabeçalho Verde Gradiente */
        .top-banner {
            background: linear-gradient(135deg, #14532d 0%, #15803d 60%, #16a34a 100%);
            color: #ffffff;
            padding: 26px 20px 38px;
            border-bottom-left-radius: 26px;
            border-bottom-right-radius: 26px;
        }
        .banner-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .resp-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 1.5px solid rgba(255, 255, 255, 0.4);
            display: flex; justify-content: center; align-items: center;
            font-weight: 700; font-size: 17px; color: #fff;
        }
        .greeting h2 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .greeting p { font-size: 12px; color: #bbf7d0; margin-top: 2px; }
        .btn-bell {
            position: relative;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            color: #fff; font-size: 16px; cursor: pointer;
        }
        .badge-notif {
            position: absolute; top: -2px; right: -2px;
            background: #ef4444; color: #fff;
            font-size: 10px; font-weight: 700;
            width: 17px; height: 17px; border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
        }
        .banner-summary {
            background: rgba(0, 0, 0, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .banner-summary span { font-size: 13px; color: #dcfce7; }
        .banner-summary strong { font-size: 15px; font-weight: 700; color: #ffffff; }

        /* Conteúdo Principal */
        .main-content { margin-top: -18px; padding: 0 16px; }

        .dep-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 14px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
            border: 1px solid #e5e7eb;
        }
        .dep-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .dep-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: #dcfce7; color: #15803d;
            font-weight: 700; font-size: 16px;
            display: flex; justify-content: center; align-items: center;
            flex-shrink: 0;
        }
        .dep-info h3 { font-size: 15px; font-weight: 700; color: #111827; }
        .dep-info p { font-size: 12px; color: #6b7280; margin-top: 2px; }

        .balance-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .balance-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .balance-label { font-size: 12px; color: #6b7280; font-weight: 500; }
        .balance-value { font-size: 19px; font-weight: 800; color: #15803d; }

        .limit-row { display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #6b7280; margin-bottom: 5px; }
        .limit-value { font-weight: 600; color: #374151; }
        .progress-bg { width: 100%; height: 6px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
        .progress-fill { height: 100%; background: #16a34a; border-radius: 99px; transition: width 0.3s; }

        .dep-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .action-btn {
            display: flex; align-items: center; justify-content: center; gap: 5px;
            border: none; cursor: pointer;
            color: #1f2937; font-size: 12px; font-weight: 600;
            padding: 10px 6px; border-radius: 10px; background: #f3f4f6;
            transition: all 0.2s;
        }
        .action-btn:hover { background: #dcfce7; color: #15803d; }
        .action-btn.primary { background: #15803d; color: #ffffff; }
        .action-btn.primary:hover { background: #166534; }
        .btn-vincular {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 15px;
            background: #ffffff; border: 1.5px dashed #86efac;
            border-radius: 14px; color: #15803d;
            font-size: 13px; font-weight: 600; cursor: pointer;
            margin-bottom: 18px; transition: all 0.2s;
        }
        .btn-vincular:hover { background: #f0fdf4; border-color: #16a34a; }
        .plus-circle {
            width: 24px; height: 24px; border-radius: 50%;
            background: #dcfce7; display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: #15803d; font-weight: 700;
        }

        .section-title { font-size: 14px; font-weight: 700; color: #374151; margin-bottom: 10px; padding-left: 2px; }

        .shortcuts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .shortcut-card {
            background: #ffffff; border: 1px solid #e5e7eb;
            border-radius: 14px; padding: 14px;
            display: flex; align-items: center; justify-content: space-between;
            cursor: pointer; text-align: left; color: #1f2937;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            transition: all 0.15s;
        }
        .shortcut-card:hover { border-color: #86efac; background: #f0fdf4; }
        .shortcut-left { display: flex; align-items: center; gap: 10px; }
        .shortcut-icon {
            width: 34px; height: 34px; border-radius: 10px;
            background: #f0fdf4; display: flex; align-items: center; justify-content: center;
            font-size: 17px; flex-shrink: 0;
        }
        .shortcut-title { font-size: 12.5px; font-weight: 600; line-height: 1.25; }
        .arrow { color: #9ca3af; font-size: 13px; }

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
            text-decoration: none; background: none; border: none; cursor: pointer;
            color: #6b7280; font-size: 11px; font-weight: 600;
        }
        .nav-item.active { color: #15803d; }
        .nav-icon { font-size: 18px; }

        /* Modais (Janelas Flutuantes) */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(17, 24, 39, 0.6); z-index: 200;
            justify-content: center; align-items: flex-end;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #ffffff; width: 100%; max-width: 500px;
            border-top-left-radius: 24px; border-top-right-radius: 24px;
            padding: 24px 20px 30px; max-height: 85vh; overflow-y: auto;
            animation: slideUp 0.22s ease-out;
        }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .modal-header h3 { font-size: 17px; font-weight: 700; color: #111827; }
        .btn-close { background: #f3f4f6; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 16px; cursor: pointer; color: #4b5563; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .form-input, .form-select {
            width: 100%; padding: 12px 14px; font-size: 15px;
            border: 1.5px solid #d1d5db; border-radius: 12px;
            margin-bottom: 14px; outline: none;
        }
        .form-input:focus, .form-select:focus { border-color: #15803d; }
        .quick-values { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
        .quick-btn {
            padding: 10px 4px; background: #f0fdf4; border: 1px solid #bbf7d0;
            border-radius: 10px; color: #15803d; font-weight: 700; font-size: 13px; cursor: pointer;
        }
        .quick-btn:hover { background: #dcfce7; }
        .btn-confirm {
            width: 100%; padding: 14px; background: #15803d; color: #fff;
            border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer;
        }
        .btn-confirm:hover { background: #166534; }
        .trans-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 0; border-bottom: 1px solid #f3f4f6;
        }
        .trans-desc { font-size: 13.5px; font-weight: 600; color: #111827; }
        .trans-date { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .trans-val.entrada { color: #15803d; font-weight: 700; font-size: 14px; }
        .trans-val.saida { color: #dc2626; font-weight: 700; font-size: 14px; }
    </style>
</head>
<body>

<div class="app-shell">
    <!-- Cabeçalho Verde -->
    <div class="top-banner">
        <div class="banner-top">
            <div class="user-info">
                <div class="resp-avatar"><?php echo strtoupper(substr($primeiro_nome, 0, 1)); ?></div>
                <div class="greeting">
                    <h2>Olá, <?php echo htmlspecialchars($primeiro_nome); ?> 👋</h2>
                    <p>Painel da Família • Banco EEP</p>
                </div>
            </div>
            <button type="button" class="btn-bell" onclick="abrirModal('modal-servicos', 'Notificações da Escola', 'Nenhuma nova notificação pendente no momento.')">
                🔔
                <span class="badge-notif">0</span>
            </button>
        </div>

        <div class="banner-summary">
            <span>Dependentes vinculados</span>
            <strong><?php echo $total_dependentes; ?> <?php echo $total_dependentes === 1 ? 'estudante' : 'estudantes'; ?></strong>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <div class="main-content">

        <?php if ($total_dependentes === 0): ?>
            <div class="dep-card" style="text-align: center; padding: 28px 18px;">
                <p style="color: #6b7280; font-size: 14px;">Nenhum dependente vinculado ainda.</p>
            </div>
        <?php else: ?>
            <?php foreach ($dependentes as $dep): 
                $ra_dep     = $dep['ra'] ?? '';
                $saldo_num  = (float)($dep['saldo'] ?? 0);
                $limite_num = (float)($dep['limite_diario'] ?? 20);
                $gasto_hoje = (float)($dep['gasto_hoje'] ?? 0);
                $porcentagem = $limite_num > 0 ? min(100, ($gasto_hoje / $limite_num) * 100) : 0;

                $saldo  = number_format($saldo_num, 2, ',', '.');
                $limite = number_format($limite_num, 2, ',', '.');
                $gasto  = number_format($gasto_hoje, 2, ',', '.');

                $escola = !empty($dep['escola']) ? $dep['escola'] : 'RA: ' . $ra_dep;
                $iniciais = strtoupper(substr(trim($dep['nome_filho']), 0, 1));
            ?>
            <div class="dep-card">
                <div class="dep-header">
                    <div class="dep-avatar"><?php echo $iniciais; ?></div>
                    <div class="dep-info">
                        <h3><?php echo htmlspecialchars($dep['nome_filho']); ?></h3>
                        <p><?php echo htmlspecialchars('RA: ' . $ra_dep . ' • ' . $escola); ?></p>
                    </div>
                </div>

                <div class="balance-box">
                    <div class="balance-row">
                        <span class="balance-label">Saldo disponível</span>
                        <span class="balance-value">R$ <?php echo $saldo; ?></span>
                    </div>

                    <div class="limit-row">
                        <span>Limite diário utilizado</span>
                        <span class="limit-value">R$ <?php echo $gasto; ?> / R$ <?php echo $limite; ?></span>
                    </div>
                    <div class="progress-bg">
                        <div class="progress-fill" style="width: <?php echo $porcentagem; ?>%;"></div>
                    </div>
                </div>

                <div class="dep-actions">
                    <button type="button" class="action-btn primary" onclick="abrirRecarga('<?php echo htmlspecialchars($ra_dep); ?>', '<?php echo htmlspecialchars($dep['nome_filho']); ?>')">💸 Recarregar</button>
                    <button type="button" class="action-btn" onclick="abrirExtrato('<?php echo htmlspecialchars($ra_dep); ?>', '<?php echo htmlspecialchars($dep['nome_filho']); ?>')">📄 Extrato</button>
                    <button type="button" class="action-btn" onclick="abrirControle('<?php echo htmlspecialchars($ra_dep); ?>', '<?php echo htmlspecialchars($dep['nome_filho']); ?>', '<?php echo $limite_num; ?>')">🛡️ Controle</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <button type="button" class="btn-vincular" onclick="abrirModal('modal-vincular')">
            <span class="plus-circle">+</span>
            Vincular outro dependente
        </button>

        <div class="section-title">Serviços escolares</div>

        <div class="shortcuts-grid">
            <button type="button" class="shortcut-card" onclick="abrirModal('modal-servicos', 'Pagamentos Escolares', 'Nenhum boleto ou taxa escolar em aberto no momento.')">
                <div class="shortcut-left">
                    <span class="shortcut-icon">🏫</span>
                    <span class="shortcut-title">Pagamentos<br>escolares</span>
                </div>
                <span class="arrow">→</span>
            </button>

            <button type="button" class="shortcut-card" onclick="abrirModal('modal-servicos', 'Autorizações Digitais', 'Nenhuma solicitação de passeio ou evento aguardando assinatura.')">
                <div class="shortcut-left">
                    <span class="shortcut-icon">✍️</span>
                    <span class="shortcut-title">Autorizações<br>digitais</span>
                </div>
                <span class="arrow">→</span>
            </button>

            <button type="button" class="shortcut-card" onclick="abrirModal('modal-servicos', 'Mensagens da Escola', 'Caixa de mensagens vazia. Comunicados da direção aparecerão aqui.')">
                <div class="shortcut-left">
                    <span class="shortcut-icon">💬</span>
                    <span class="shortcut-title">Mensagens da<br>escola</span>
                </div>
                <span class="arrow">→</span>
            </button>

            <button type="button" class="shortcut-card" onclick="abrirModal('modal-servicos', 'Notificações', 'Todas as recargas e compras dos seus dependentes são notificadas aqui.')">
                <div class="shortcut-left">
                    <span class="shortcut-icon">🔔</span>
                    <span class="shortcut-title">Notificações</span>
                </div>
                <span class="arrow">→</span>
            </button>
        </div>
    </div>
    <!-- Menu Inferior Fixo -->
    <nav class="bottom-nav">
        <a href="painel-responsavel.php" class="nav-item active">
            <span class="nav-icon">🏠</span>
            <span>Início</span>
        </a>
        <button type="button" class="nav-item" onclick="abrirRecarga('', '')">
            <span class="nav-icon">💸</span>
            <span>Enviar</span>
        </button>
        <button type="button" class="nav-item" onclick="abrirControle('', '', 20)">
            <span class="nav-icon">🛡️</span>
            <span>Controle</span>
        </button>
        <button type="button" class="nav-item" onclick="abrirExtrato('', '')">
            <span class="nav-icon">📄</span>
            <span>Extrato</span>
        </button>
        <a href="perfil-responsavel.php" class="nav-item">
            <span class="nav-icon">👤</span>
            <span>Perfil</span>
</a>

    </nav>
</div>

<!-- MODAL 1: RECARREGAR SALDO -->
<div id="modal-recarga" class="modal-overlay" onclick="fecharFora(event, 'modal-recarga')">
    <div class="modal-box">
        <div class="modal-header">
            <h3>💸 Recarregar Saldo</h3>
            <button type="button" class="btn-close" onclick="fecharModal('modal-recarga')">✕</button>
        </div>
        <form action="../actions/acoes-responsavel.php" method="POST">
            <input type="hidden" name="acao" value="recarregar">
            
            <label class="form-label">Selecione o dependente</label>
            <select name="ra" id="recarga-ra" class="form-select" required>
                <?php foreach ($dependentes as $dep): ?>
                    <option value="<?php echo htmlspecialchars($dep['ra']); ?>">
                        <?php echo htmlspecialchars($dep['nome_filho'] . ' (RA: ' . $dep['ra'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="form-label">Escolha rápida</label>
            <div class="quick-values">
                <button type="button" class="quick-btn" onclick="setValorRecarga('10.00')">+ R$ 10</button>
                <button type="button" class="quick-btn" onclick="setValorRecarga('20.00')">+ R$ 20</button>
                <button type="button" class="quick-btn" onclick="setValorRecarga('30.00')">+ R$ 30</button>
                <button type="button" class="quick-btn" onclick="setValorRecarga('50.00')">+ R$ 50</button>
            </div>

            <label class="form-label">Ou digite o valor (R$)</label>
            <input type="number" step="0.01" min="1" name="valor" id="recarga-valor" class="form-input" placeholder="Ex: 25.00" required>

            <button type="submit" class="btn-confirm">Confirmar Recarga</button>
        </form>
    </div>
</div>

<!-- MODAL 2: CONTROLE DE LIMITE DIÁRIO -->
<div id="modal-controle" class="modal-overlay" onclick="fecharFora(event, 'modal-controle')">
    <div class="modal-box">
        <div class="modal-header">
            <h3>🛡️ Limite Diário de Gastos</h3>
            <button type="button" class="btn-close" onclick="fecharModal('modal-controle')">✕</button>
        </div>
        <form action="../actions/acoes-responsavel.php" method="POST">
            <input type="hidden" name="acao" value="limite">

            <label class="form-label">Selecione o dependente</label>
            <select name="ra" id="controle-ra" class="form-select" required>
                <?php foreach ($dependentes as $dep): ?>
                    <option value="<?php echo htmlspecialchars($dep['ra']); ?>">
                        <?php echo htmlspecialchars($dep['nome_filho']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="form-label">Novo limite diário (R$)</label>
            <input type="number" step="0.01" min="0" name="limite_diario" id="controle-limite" class="form-input" placeholder="Ex: 20.00" required>

            <button type="submit" class="btn-confirm">Salvar Novo Limite</button>
        </form>
    </div>
</div>

<!-- MODAL 3: EXTRATO DO DEPENDENTE -->
<div id="modal-extrato" class="modal-overlay" onclick="fecharFora(event, 'modal-extrato')">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="extrato-titulo">📄 Extrato do Estudante</h3>
            <button type="button" class="btn-close" onclick="fecharModal('modal-extrato')">✕</button>
        </div>
        <div id="extrato-lista"></div>
    </div>
</div>

<!-- MODAL 4: VINCULAR OUTRO DEPENDENTE -->
<div id="modal-vincular" class="modal-overlay" onclick="fecharFora(event, 'modal-vincular')">
    <div class="modal-box">
        <div class="modal-header">
            <h3>➕ Vincular Dependente</h3>
            <button type="button" class="btn-close" onclick="fecharModal('modal-vincular')">✕</button>
        </div>
        <form action="../actions/acoes-responsavel.php" method="POST">
            <input type="hidden" name="acao" value="vincular">
            <label class="form-label">Matrícula (RA) do Estudante</label>
            <input type="text" name="ra" class="form-input" placeholder="Digite o RA cadastrado no Banco EEP" required>
            <button type="submit" class="btn-confirm">Vincular Estudante</button>
        </form>
    </div>
</div>

<!-- MODAL 5: SERVIÇOS ESCOLARES / AVISOS -->
<div id="modal-servicos" class="modal-overlay" onclick="fecharFora(event, 'modal-servicos')">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="servicos-titulo">Aviso</h3>
            <button type="button" class="btn-close" onclick="fecharModal('modal-servicos')">✕</button>
        </div>
        <p id="servicos-texto" style="font-size: 14px; color: #4b5563; line-height: 1.5; margin-bottom: 16px;"></p>
        <button type="button" class="btn-confirm" onclick="fecharModal('modal-servicos')">Entendi</button>
    </div>
</div>

<script>
    const extratosBanco = <?php echo json_encode($extratos_por_ra, JSON_UNESCAPED_UNICODE); ?>;
    const dependentesLista = <?php echo json_encode($dependentes, JSON_UNESCAPED_UNICODE); ?>;

    function abrirModal(id, titulo = '', texto = '') {
        if (titulo && document.getElementById('servicos-titulo')) {
            document.getElementById('servicos-titulo').textContent = titulo;
        }
        if (texto && document.getElementById('servicos-texto')) {
            document.getElementById('servicos-texto').textContent = texto;
        }
        document.getElementById(id).classList.add('open');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function fecharFora(e, id) {
        if (e.target.id === id) fecharModal(id);
    }

    function setValorRecarga(val) {
        document.getElementById('recarga-valor').value = val;
    }

    function abrirRecarga(ra, nome) {
        if (dependentesLista.length === 0) {
            alert('Vincule um dependente primeiro!');
            return;
        }
        if (ra) document.getElementById('recarga-ra').value = ra;
        abrirModal('modal-recarga');
    }

    function abrirControle(ra, nome, limiteAtual) {
        if (dependentesLista.length === 0) {
            alert('Vincule um dependente primeiro!');
            return;
        }
        if (ra) document.getElementById('controle-ra').value = ra;
        document.getElementById('controle-limite').value = parseFloat(limiteAtual || 20).toFixed(2);
        abrirModal('modal-controle');
    }

    function abrirExtrato(ra, nome) {
        if (dependentesLista.length === 0) {
            alert('Vincule um dependente primeiro!');
            return;
        }
        const raAlvo = ra || dependentesLista.at(0).ra;
        const nomeAlvo = nome || dependentesLista.at(0).nome_filho;


        document.getElementById('extrato-titulo').textContent = '📄 Extrato • ' + nomeAlvo;
        const lista = extratosBanco[raAlvo] || [];
        const container = document.getElementById('extrato-lista');

        if (lista.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#6b7280; padding:20px 0; font-size:14px;">Nenhuma movimentação registrada ainda.</p>';
        } else {
            container.innerHTML = lista.map(item => {
                const isEntrada = item.tipo === 'entrada';
                const sinal = isEntrada ? '+' : '-';
                const valorFmt = parseFloat(item.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                return `
                    <div class="trans-item">
                        <div>
                            <div class="trans-desc">${item.descricao}</div>
                            <div class="trans-date">${item.categoria} • ${item.data_hora}</div>
                        </div>
                        <div class="trans-val ${isEntrada ? 'entrada' : 'saida'}">${sinal} R$ ${valorFmt}</div>
                    </div>
                `;
            }).join('');
        }
        abrirModal('modal-extrato');
    }
</script>
</body>
</html>

