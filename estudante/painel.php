<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bloqueia quem não está logado
if (!isset($_SESSION['ra'])) {
    header("Location: login-estudante.php?erro=Acesso+negado");
    exit();
}

$servidor = "localhost";
$usuario  = "root";
$senha_db = "";
$banco    = "banco-eep";

$conexao = new mysqli($servidor, $usuario, $senha_db, $banco);
if ($conexao->connect_error) {
    die("Falha na conexão: " . $conexao->connect_error);
}
$conexao->set_charset("utf8mb4");

$ra_logado = $conexao->real_escape_string($_SESSION['ra']);

// 1. Busca os dados do estudante logado
$stmt = $conexao->prepare("SELECT * FROM estudantes WHERE ra = ?");
$stmt->bind_param("s", $ra_logado);
$stmt->execute();
$resultado = $stmt->get_result();
$estudante = $resultado->fetch_assoc();
$stmt->close();

// 2. Verifica status de segurança (Cartão Cancelado ou Transações Travadas)
$cartao_cancelado = false;
$transacoes_travadas = false;
$check_seg = $conexao->query("SHOW TABLES LIKE 'seguranca_conta'");
if ($check_seg && $check_seg->num_rows > 0) {
    $res_seg = $conexao->query("SELECT * FROM seguranca_conta WHERE ra = '$ra_logado'");
    if ($res_seg && $res_seg->num_rows > 0) {
        $seg = $res_seg->fetch_assoc();
        $cartao_cancelado = ($seg['cartao_status'] === 'cancelado');
        $transacoes_travadas = ((int)$seg['transacoes_travadas'] === 1);
    }
}

// 3. Busca a meta ativa mais próxima do estudante no banco
$meta_ativa = null;
$check_metas = $conexao->query("SHOW TABLES LIKE 'metas'");
if ($check_metas && $check_metas->num_rows > 0) {
    $res_meta = $conexao->query("SELECT * FROM metas WHERE ra = '$ra_logado' AND valor_atual < valor_alvo ORDER BY prazo ASC LIMIT 1");
    if ($res_meta && $res_meta->num_rows > 0) {
        $meta_ativa = $res_meta->fetch_assoc();
    }
}

// 4. Busca últimas transações reais (se a tabela existir)
$ultimos_gastos = [];
$check_trans = $conexao->query("SHOW TABLES LIKE 'transacoes'");
if ($check_trans && $check_trans->num_rows > 0) {
    $res_t = $conexao->query("SELECT * FROM transacoes WHERE ra = '$ra_logado' ORDER BY id DESC LIMIT 3");
    if ($res_t) {
        while ($row = $res_t->fetch_assoc()) {
            $ultimos_gastos[] = $row;
        }
    }
}

if (empty($ultimos_gastos)) {
    $ultimos_gastos = [
        ['descricao' => 'Cantina do Seu Jorge', 'valor' => 6.00, 'tipo' => 'saida', 'data_hora' => '2026-11-23 10:15:00'],
        ['descricao' => 'Cantina do Seu Jorge', 'valor' => 4.00, 'tipo' => 'saida', 'data_hora' => '2026-11-23 10:15:00'],
        ['descricao' => 'Papelaria da Dona Maria', 'valor' => 5.00, 'tipo' => 'saida', 'data_hora' => '2026-11-21 14:20:00']
    ];
}

// Calcula barra do limite diário
$limite_total = isset($estudante['limite_diario']) ? (float)$estudante['limite_diario'] : 20.00;
$limite_restante = min($limite_total, max(0, $limite_total - 8.50));
$pct_limite = $limite_total > 0 ? round(($limite_restante / $limite_total) * 100) : 0;

$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Painel do Estudante</title>
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        
        body { 
            background-color: #f1f5f9; min-height: 100vh; color: #1e293b; 
            display: flex; justify-content: center; align-items: stretch; margin: 0;
        }
        .app-container { 
            width: 100%; max-width: 800px; background: #fff; min-height: 100vh; 
            display: flex; flex-direction: column; position: relative; padding-bottom: 95px; 
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        .nav-bar { 
            position: fixed; bottom: 0; width: 100%; max-width: 800px; background: white; 
            border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: repeat(5, 1fr); 
            padding: 10px 0; text-align: center; z-index: 1000; 
        }

        @media (min-width: 768px) {
            body { padding: 20px 0; }
            .app-container {
                max-width: 800px; min-height: calc(100vh - 40px);
                box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-radius: 24px; overflow: hidden;
            }
            .nav-bar {
                max-width: 800px; border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;
            }
        }
        
        /* Topo Azul */
        .header-card { background: linear-gradient(135deg, hsl(222, 84.4%, 30.2%), #061d5c); color: white; padding: 24px 20px; border-bottom-left-radius: 28px; border-bottom-right-radius: 28px; }
        .top-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .user-profile-top { display: flex; align-items: center; gap: 12px; }
        .top-avatar {
            width: 46px; height: 46px; border-radius: 50%; background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center; font-size: 22px;
            overflow: hidden; border: 2px solid rgba(255,255,255,0.6); flex-shrink: 0;
        }
        .top-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-greeting { font-size: 18px; font-weight: 700; }
        .user-school { font-size: 12px; opacity: 0.9; margin-top: 2px; }

        .notification-btn { background: rgba(255,255,255,0.2); border: none; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; position: relative; cursor: pointer; text-decoration: none; }
        .notification-badge { position: absolute; top: 4px; right: 4px; background: #ef4444; color: white; font-size: 10px; font-weight: 700; width: 16px; height: 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

        /* Saldo */
        .balance-box { background: rgba(255, 255, 255, 0.12); padding: 18px; border-radius: 16px; margin-bottom: 16px; }
        .balance-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; font-weight: 600; }
        .balance-value { font-size: 32px; font-weight: 700; margin: 4px 0; }
        .balance-header-row { display: flex; justify-content: space-between; align-items: center; }
        .btn-eye { background: rgba(255,255,255,0.18); border: none; color: white; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; transition: 0.2s; }
        .btn-eye:hover { background: rgba(255,255,255,0.3); }

        .balance-meta { font-size: 12px; opacity: 0.9; }

        /* Limite Diário */
        .limit-box { font-size: 12px; }
        .limit-header { display: flex; justify-content: space-between; margin-bottom: 6px; opacity: 0.9; }
        .progress-bar { background: rgba(255,255,255,0.2); height: 7px; border-radius: 4px; overflow: hidden; }
        .progress-fill { background: #4ade80; height: 100%; border-radius: 4px; }

        /* Banner de Alerta de Segurança */
        .security-banner {
            margin: 16px 20px 0 20px; padding: 12px 16px; border-radius: 14px;
            background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;
            font-size: 13px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; gap: 10px;
        }
        .security-banner a { color: #b91c1c; text-decoration: underline; white-space: nowrap; }

        /* Corpo de Conteúdo */
        .content-body { padding: 20px; flex: 1; }

        /* Cartão Virtual do Estudante com Foto */
        .student-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%);
            color: white; border-radius: 20px; padding: 20px; margin-bottom: 22px;
            box-shadow: 0 10px 20px rgba(30, 58, 138, 0.2); position: relative; overflow: hidden;
        }
        .student-card.blocked {
            background: linear-gradient(135deg, #450a0a 0%, #991b1b 100%);
            opacity: 0.92;
        }
        .card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .card-brand { font-size: 15px; font-weight: 800; letter-spacing: 1px; }
        .card-chip { font-size: 11px; background: rgba(255,255,255,0.18); padding: 4px 10px; border-radius: 20px; font-weight: 600; }
        .card-middle { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
        .card-photo {
            width: 64px; height: 64px; border-radius: 14px; background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center;
            font-size: 30px; overflow: hidden; flex-shrink: 0;
        }
        .card-photo img { width: 100%; height: 100%; object-fit: cover; }
        .card-holder-name { font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-number { font-size: 13px; opacity: 0.9; margin-top: 4px; letter-spacing: 1.5px; font-family: monospace; }
        .card-bottom { display: flex; justify-content: space-between; align-items: center; font-size: 11px; opacity: 0.85; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 10px; }
        .btn-toggle-card { background: rgba(255,255,255,0.2); border: none; color: white; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; cursor: pointer; }
        
        /* Ações Rápidas */
        .quick-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; text-align: center; }
        .action-item { display: flex; flex-direction: column; align-items: center; gap: 8px; text-decoration: none; color: #334155; }
        .action-icon { width: 100%; aspect-ratio: 1; max-width: 72px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 22px; transition: 0.2s; }
        .action-item:hover .action-icon { border-color: #2563eb; background: #eff6ff; }
        .action-name { font-size: 12px; font-weight: 600; }

        /* Meta Ativa */
        .goal-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; margin-bottom: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .goal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .goal-title { font-size: 15px; font-weight: 700; color: #0f172a; }
        .goal-subtitle { font-size: 12px; color: #64748b; }
        .goal-progress-bar { background: #f1f5f9; height: 7px; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
        .goal-progress-fill { background: #10b981; height: 100%; border-radius: 4px; }
        .goal-footer { display: flex; justify-content: space-between; font-size: 12px; color: #64748b; font-weight: 500; }

        /* Últimos Gastos */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .section-title { font-size: 17px; font-weight: 700; color: #0f172a; }
        .section-link { font-size: 13px; color: #2563eb; text-decoration: none; font-weight: 600; }
        
        .transaction-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
        .transaction-left { display: flex; align-items: center; gap: 14px; }
        .transaction-icon { width: 44px; height: 44px; background: #fff1f2; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .transaction-icon.positive { background: #ecfdf5; }
        .transaction-name { font-size: 14px; font-weight: 600; color: #0f172a; }
        .transaction-date { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .transaction-value { font-size: 14px; font-weight: 700; color: #ef4444; }
        .transaction-value.positive { color: #10b981; }

        /* Itens do Menu Inferior */
        .nav-item { display: flex; flex-direction: column; align-items: center; gap: 4px; text-decoration: none; color: #94a3b8; font-size: 11px; font-weight: 500; }
        .nav-item.active { color: #2563eb; font-weight: 700; }
        .nav-item span { font-size: 20px; }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Topo Azul -->
        <div class="header-card">
            <div class="top-row">
                <div class="user-profile-top">
                    <div class="top-avatar">
                        <?php if (!empty($estudante['foto']) && file_exists($estudante['foto'])): ?>
                            <img src="<?= htmlspecialchars($estudante['foto']) ?>" alt="Foto">
                        <?php else: ?>
                            🎒
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="user-greeting">Olá, <?= htmlspecialchars($estudante['nome_completo']) ?> 👋</div>
                        <div class="user-school">Escola: <?= htmlspecialchars($estudante['escola']) ?></div>
                    </div>
                </div>
                <a href="perfil.php" class="notification-btn" title="Central de Segurança e Suporte">
                    🔔
                    <span class="notification-badge"><?= ($cartao_cancelado || $transacoes_travadas) ? '!' : '0' ?></span>
                </a>
            </div>

            <div class="balance-box">
    <div class="balance-header-row">
        <div class="balance-label">Saldo Disponível</div>
        <button type="button" class="btn-eye" id="btnOlhoSaldo" onclick="alternarVisibilidadeSaldo()" title="Ocultar ou mostrar valores">
            👁️
        </button>
    </div>
    <div class="balance-value" id="saldo-atual" data-valor="R$ <?= number_format((float)$estudante['saldo'], 2, ',', '.') ?>">
        R$ <?= number_format((float)$estudante['saldo'], 2, ',', '.') ?>
    </div>
    <div class="balance-meta">RA: <?= htmlspecialchars($estudante['ra']) ?></div>
    </div>


            <div class="limit-box">
                <div class="limit-header">
                    <span>Limite diário restante</span>
                    <span>R$ <?= number_format($limite_restante, 2, ',', '.') ?> de R$ <?= number_format($limite_total, 2, ',', '.') ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $pct_limite ?>%;"></div>
                </div>
            </div>
        </div>

        <!-- Alerta caso o cartão esteja cancelado ou transações travadas -->
        <?php if ($cartao_cancelado): ?>
            <div class="security-banner">
                <span>🚨 Seu cartão está CANCELADO por perda/roubo.</span>
                <a href="perfil.php">Solicitar 2ª via</a>
            </div>
        <?php elseif ($transacoes_travadas): ?>
            <div class="security-banner">
                <span>🔒 Transações temporariamente bloqueadas.</span>
                <a href="perfil.php">Destravar</a>
            </div>
        <?php endif; ?>

        <div class="content-body">
            <!-- Cartão Virtual Estudantil EEP com a Foto do Estudante -->
            <div class="student-card <?= ($cartao_cancelado || $transacoes_travadas) ? 'blocked' : '' ?>">
                <div class="card-top">
                    <div class="card-brand">💳 BANCO EEP • CARTÃO ESCOLAR</div>
                    <div class="card-chip">
                        <?= $cartao_cancelado ? 'CANCELADO' : ($transacoes_travadas ? 'BLOQUEADO' : 'ATIVO') ?>
                    </div>
                </div>

                <div class="card-middle">
                    <div class="card-photo">
                        <?php if (!empty($estudante['foto']) && file_exists($estudante['foto'])): ?>
                            <img src="<?= htmlspecialchars($estudante['foto']) ?>" alt="Foto do Titular">
                        <?php else: ?>
                            🧑
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="card-holder-name"><?= htmlspecialchars($estudante['nome_completo']) ?></div>
                        <div class="card-number" id="numeroCartao">•••• •••• •••• <?= substr(preg_replace('/\D/', '', $estudante['ra']), -4) ?: '2026' ?></div>
                        <div style="font-size: 11px; opacity: 0.8; margin-top: 3px;">RA: <?= htmlspecialchars($estudante['ra']) ?></div>
                    </div>
                </div>

                <div class="card-bottom">
                    <span>Validade: 12/2029 • Uso Escolar</span>
                    <button type="button" class="btn-toggle-card" onclick="alternarNumeroCartao()">👁️ Ver dados</button>
                </div>
            </div>

            <!-- Atalhos Rápidos -->
            <div class="quick-actions">
                <a href="educacao-financeira.php" class="action-item">
                    <div class="action-icon">📖</div>
                    <div class="action-name">Educação</div>
                </a>
                <a href="extrato.php" class="action-item">
                    <div class="action-icon">📄</div>
                    <div class="action-name">Extrato</div>
                </a>
                <a href="metas.php" class="action-item">
                    <div class="action-icon">🎯</div>
                    <div class="action-name">Metas</div>
                </a>
                <a href="perfil.php" class="action-item">
                    <div class="action-icon">👤</div>
                    <div class="action-name">Perfil</div>
                </a>
            </div>
            <!-- Meta Ativa Real do Banco -->
            <div class="goal-card">
                <?php if ($meta_ativa): 
                    $alvo = (float)$meta_ativa['valor_alvo'];
                    $atual = (float)$meta_ativa['valor_atual'];
                    $pct_meta = $alvo > 0 ? min(100, round(($atual / $alvo) * 100)) : 0;
                ?>
                    <div class="goal-header">
                        <div>
                            <div class="goal-title"><?= htmlspecialchars($meta_ativa['icone']) ?> <?= htmlspecialchars($meta_ativa['titulo']) ?></div>
                            <div class="goal-subtitle">Meta ativa • <?= $pct_meta ?>% concluída</div>
                        </div>
                        <a href="metas.php" class="section-link">Guardar +</a>
                    </div>
                    <div class="goal-progress-bar">
                        <div class="goal-progress-fill" style="width: <?= $pct_meta ?>%;"></div>
                    </div>
                    <div class="goal-footer">
                        <span>R$ <?= number_format($atual, 2, ',', '.') ?> guardados</span>
                        <span>Objetivo: R$ <?= number_format($alvo, 2, ',', '.') ?></span>
                    </div>
                <?php else: ?>
                    <div class="goal-header" style="margin-bottom: 0;">
                        <div>
                            <div class="goal-title">🎯 Nenhuma meta ativa no momento</div>
                            <div class="goal-subtitle">Crie um objetivo para começar a poupar</div>
                        </div>
                        <a href="metas.php" class="section-link">+ Criar Meta</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Últimos Gastos / Movimentações -->
            <div class="section-header">
                <div class="section-title">Últimas movimentações</div>
                <a href="extrato.php" class="section-link">Ver tudo</a>
            </div>

            <?php foreach ($ultimos_gastos as $gasto): 
                $is_entrada = (isset($gasto['tipo']) && $gasto['tipo'] === 'entrada');
                $icone = $is_entrada ? '💰' : '🛍️';
                if (stripos($gasto['descricao'], 'Cantina') !== false) $icone = '🌭';
                elseif (stripos($gasto['descricao'], 'Papelaria') !== false) $icone = '✏️';
                elseif (stripos($gasto['descricao'], 'Bônus') !== false) $icone = '🎓';
                $data_fmt = date('d/m, H:i', strtotime($gasto['data_hora']));
            ?>
                <div class="transaction-item">
    <div class="transaction-left">
        <div class="transaction-icon <?= $is_entrada ? 'positive' : '' ?>" style="overflow: hidden;">
            <?php if (!empty($gasto['imagem_produto']) && file_exists($gasto['imagem_produto'])): ?>
                <img src="<?= htmlspecialchars($gasto['imagem_produto']) ?>" alt="Produto" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <?= $icone ?>
            <?php endif; ?>
        </div>
<div>
     <div class="transaction-name"><?= htmlspecialchars($gasto['descricao']) ?></div>
     <div class="transaction-date"><?= $data_fmt ?></div>
</div>
 </div>
    <div class="transaction-value <?= $is_entrada ? 'positive' : '' ?>">
        <?= $is_entrada ? '+' : '-' ?>R$ <?= number_format((float)$gasto['valor'], 2, ',', '.') ?>
    </div>
</div>

            <?php endforeach; ?>
        </div>

        <!-- Menu Inferior Fixo -->
        <nav class="nav-bar">
            <a href="painel.php" class="nav-item active"><span>🏠</span>Início</a>
            <a href="educacao-financeira.php" class="nav-item"><span>📖</span>Educação</a>
            <a href="extrato.php" class="nav-item"><span>📄</span>Extrato</a>
            <a href="metas.php" class="nav-item"><span>🎯</span>Metas</a>
            <a href="perfil.php" class="nav-item"><span>👤</span>Perfil</a>
        </nav>
    </div>

    <script>
        let numeroVisivel = false;
        const raCompleto = "<?= preg_replace('/\D/', '', $estudante['ra']) ?>";
        const ultimos4 = raCompleto.slice(-4) || "2026";

        function alternarNumeroCartao() {
            const el = document.getElementById('numeroCartao');
            numeroVisivel = !numeroVisivel;
            if (numeroVisivel) {
                el.innerText = "5412 8800 " + (raCompleto.slice(0, 4) || "1020") + " " + ultimos4;
            } else {
                el.innerText = "•••• •••• •••• " + ultimos4;
            }
        }

        function atualizarPainel() {
            fetch('verificar-atualizacoes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.erro) return;
                    const elementoSaldo = document.getElementById('saldo-atual');
                    if (elementoSaldo && data.saldo) {
                        elementoSaldo.innerText = "R$ " + data.saldo;
                    }
                    if (data.notificacoes && data.notificacoes.length > 0) {
                        data.notificacoes.forEach(notif => {
                            alert("🔔 Nova Notificação: " + notif.mensagem);
                        });
                    }
                })
                .catch(error => console.error('Erro ao atualizar:', error));
        }
        setInterval(atualizarPainel, 4000);
        let saldoOculto = localStorage.getItem('eep_saldo_oculto') === 'true';

function aplicarEstadoSaldo() {
    const elSaldo = document.getElementById('saldo-atual');
    const elLimite = document.getElementById('texto-limite');
    const btnOlho = document.getElementById('btnOlhoSaldo');

    if (saldoOculto) {
        elSaldo.innerText = 'R$ ••••••';
        if (elLimite) elLimite.innerText = '••••••';
        btnOlho.innerText = '🙈';
    } else {
        elSaldo.innerText = elSaldo.getAttribute('data-valor');
        if (elLimite) elLimite.innerText = elLimite.getAttribute('data-valor');
        btnOlho.innerText = '👁️';
    }
}

function alternarVisibilidadeSaldo() {
    saldoOculto = !saldoOculto;
    localStorage.setItem('eep_saldo_oculto', saldoOculto);
    aplicarEstadoSaldo();
}

// Mantém a preferência salva mesmo se atualizar a página
aplicarEstadoSaldo();

    </script>
</body>
</html>
