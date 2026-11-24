<?php
session_start();

// Verifica se o estudante está logado
if (!isset($_SESSION['ra'])) {
    header("Location: login-estudante.php");
    exit;
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
$mensagem = "";
$tipo_msg = "";

// Cria a tabela de metas caso não exista
$sql_cria_tabela = "CREATE TABLE IF NOT EXISTS metas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ra VARCHAR(20) NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    icone VARCHAR(10) NOT NULL,
    prazo DATE NOT NULL,
    valor_alvo DECIMAL(10,2) NOT NULL,
    valor_atual DECIMAL(10,2) DEFAULT 0.00
)";
$conexao->query($sql_cria_tabela);

// Busca saldo atual do estudante
$saldo_disponivel = 0.00;
$res_saldo = $conexao->query("SELECT saldo FROM estudantes WHERE ra = '$ra_logado'");
if ($res_saldo && $res_saldo->num_rows > 0) {
    $row_s = $res_saldo->fetch_assoc();
    $saldo_disponivel = (float)$row_s['saldo'];
}

// Processar Ações (Nova Meta, Depósito, Resgate ou Exclusão)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. Criar Nova Meta
    if ($acao === 'nova_meta') {
        $titulo = $conexao->real_escape_string(trim($_POST['titulo']));
        $icone = $conexao->real_escape_string($_POST['icone'] ?: '🎯');
        $prazo = $conexao->real_escape_string($_POST['prazo']);
        $valor_alvo = floatval($_POST['valor_alvo']);

        if (!empty($titulo) && $valor_alvo > 0) {
            $conexao->query("INSERT INTO metas (ra, titulo, icone, prazo, valor_alvo, valor_atual) VALUES ('$ra_logado', '$titulo', '$icone', '$prazo', '$valor_alvo', 0.00)");
            $mensagem = "Meta criada com sucesso!";
            $tipo_msg = "success";
        }
    }

    // 2. Depositar na Meta (desconta do saldo principal)
    if ($acao === 'depositar') {
        $meta_id = intval($_POST['meta_id']);
        $valor_deposito = floatval($_POST['valor_deposito']);

        if ($valor_deposito <= 0) {
            $mensagem = "Digite um valor maior que zero.";
            $tipo_msg = "error";
        } elseif ($valor_deposito > $saldo_disponivel) {
            $mensagem = "Saldo insuficiente! Você tem R$ " . number_format($saldo_disponivel, 2, ',', '.') . " disponíveis.";
            $tipo_msg = "error";
        } else {
            // Desconta da conta e soma na meta
            $conexao->query("UPDATE estudantes SET saldo = saldo - $valor_deposito WHERE ra = '$ra_logado'");
            $conexao->query("UPDATE metas SET valor_atual = valor_atual + $valor_deposito WHERE id = $meta_id AND ra = '$ra_logado'");
            $saldo_disponivel -= $valor_deposito;
            $mensagem = "Depósito de R$ " . number_format($valor_deposito, 2, ',', '.') . " guardado na meta!";
            $tipo_msg = "success";
        }
    }

    // 3. Resgatar Meta Concluída (devolve o dinheiro para o saldo usar)
    if ($acao === 'resgatar') {
        $meta_id = intval($_POST['meta_id']);
        $res_m = $conexao->query("SELECT valor_atual FROM metas WHERE id = $meta_id AND ra = '$ra_logado'");
        if ($res_m && $res_m->num_rows > 0) {
            $m = $res_m->fetch_assoc();
            $valor_resgate = (float)$m['valor_atual'];
            $conexao->query("UPDATE estudantes SET saldo = saldo + $valor_resgate WHERE ra = '$ra_logado'");
            $conexao->query("DELETE FROM metas WHERE id = $meta_id AND ra = '$ra_logado'");
            $saldo_disponivel += $valor_resgate;
            $mensagem = "Parabéns! R$ " . number_format($valor_resgate, 2, ',', '.') . " resgatados para o seu saldo!";
            $tipo_msg = "success";
        }
    }

    // 4. Excluir Meta (devolve o que já estava guardado para o saldo)
    if ($acao === 'excluir') {
        $meta_id = intval($_POST['meta_id']);
        $res_m = $conexao->query("SELECT valor_atual FROM metas WHERE id = $meta_id AND ra = '$ra_logado'");
        if ($res_m && $res_m->num_rows > 0) {
            $m = $res_m->fetch_assoc();
            $valor_devolver = (float)$m['valor_atual'];
            if ($valor_devolver > 0) {
                $conexao->query("UPDATE estudantes SET saldo = saldo + $valor_devolver WHERE ra = '$ra_logado'");
                $saldo_disponivel += $valor_devolver;
            }
            $conexao->query("DELETE FROM metas WHERE id = $meta_id AND ra = '$ra_logado'");
            $mensagem = "Meta excluída e valor devolvido ao seu saldo.";
            $tipo_msg = "success";
        }
    }
}

// Buscar total poupado nas metas
$res_total = $conexao->query("SELECT SUM(valor_atual) as total FROM metas WHERE ra = '$ra_logado'");
$row_total = $res_total->fetch_assoc();
$total_poupado = $row_total['total'] ? (float)$row_total['total'] : 0.00;

// Buscar todas as metas do estudante
$resultado_metas = $conexao->query("SELECT * FROM metas WHERE ra = '$ra_logado' ORDER BY prazo ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Minhas Metas</title>
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f1f5f9; min-height: 100vh; color: #1e293b; display: flex; justify-content: center; }
        
        .app-container { 
            width: 100%; max-width: 700px; background: #fff; min-height: 100vh; 
            display: flex; flex-direction: column; position: relative; padding-bottom: 90px; 
            box-shadow: 0 0 25px rgba(0,0,0,0.08);
        }
        
        /* Topo Azul */
        .header-card { background: linear-gradient(135deg, #082258, #1d4ed8); color: white; padding: 24px 20px 28px 20px; border-bottom-left-radius: 28px; border-bottom-right-radius: 28px; }
        .top-row { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
        .back-btn { background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; font-size: 18px; cursor: pointer; }
        .header-title { font-size: 20px; font-weight: 700; }

        /* Resumo Duplo no Topo */
        .summary-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .balance-period-box { background: rgba(255, 255, 255, 0.14); padding: 16px; border-radius: 16px; text-align: center; }
        .balance-period-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85; font-weight: 600; }
        .balance-period-value { font-size: 22px; font-weight: 700; margin-top: 4px; color: #ffffff; }
        .balance-period-value.available { color: #4ade80; }

        /* Alertas */
        .alert-box { margin: 16px 20px 0 20px; padding: 14px; border-radius: 12px; font-size: 13px; font-weight: 600; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Corpo de Conteúdo */
        .content-body { padding: 20px; flex: 1; }

        /* Cartão de Meta */
        .goal-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .goal-card.completed { border-color: #10b981; background: #f0fdf4; }
        
        .goal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .goal-info-left { display: flex; align-items: center; gap: 12px; }
        .goal-icon { width: 44px; height: 44px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; border: 1px solid #e2e8f0; }
        .goal-title { font-size: 15px; font-weight: 700; color: #0f172a; }
        .goal-date { font-size: 12px; color: #64748b; margin-top: 2px; }
        
        .goal-actions-top { display: flex; align-items: center; gap: 8px; }
        .goal-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; background: #f1f5f9; color: #475569; }
        .goal-badge.completed { background: #dcfce7; color: #15803d; }
        .btn-delete-mini { background: none; border: none; color: #94a3b8; font-size: 16px; cursor: pointer; padding: 4px; }
        .btn-delete-mini:hover { color: #ef4444; }

        /* Barra de Progresso */
        .goal-progress-bar { background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 10px; }
        .goal-progress-fill { background: #10b981; height: 100%; border-radius: 4px; transition: width 0.3s ease; }
        .goal-footer { display: flex; justify-content: space-between; font-size: 12px; color: #64748b; font-weight: 500; margin-bottom: 14px; }

        /* Botões de Ação da Meta */
        .btn-deposit { width: 100%; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; padding: 12px; border-radius: 14px; font-size: 13px; font-weight: 600; cursor: pointer; text-align: center; transition: 0.2s; }
        .btn-deposit:hover { background: #dbeafe; }
        .btn-redeem { width: 100%; background: #16a34a; border: none; color: white; padding: 12px; border-radius: 14px; font-size: 13px; font-weight: 600; cursor: pointer; text-align: center; transition: 0.2s; }
        .btn-redeem:hover { background: #15803d; }

        .btn-new-goal { width: 100%; background: transparent; border: 2px dashed #cbd5e1; color: #64748b; padding: 16px; border-radius: 18px; font-size: 14px; font-weight: 600; cursor: pointer; text-align: center; margin-top: 8px; transition: 0.2s; }
        .btn-new-goal:hover { border-color: #1d4ed8; color: #1d4ed8; background: #f8fafc; }

        /* Modais e Seletores Rápidos */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 2000; justify-content: center; align-items: center; padding: 20px; }
        .modal-card { background: white; width: 100%; max-width: 420px; padding: 24px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .modal-title { font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #0f172a; }
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px; outline: none; }
        .form-input:focus { border-color: #2563eb; }

        .quick-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .chip-btn { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 10px; font-size: 14px; cursor: pointer; font-weight: 600; color: #334155; }
        .chip-btn:hover, .chip-btn.selected { background: #dbeafe; border-color: #2563eb; color: #1d4ed8; }

        .modal-buttons { display: flex; gap: 10px; margin-top: 20px; }
        .btn-save { flex: 1; background: #1d4ed8; color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer; }
        .btn-cancel { flex: 1; background: #f1f5f9; color: #64748b; border: none; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer; }

        /* Menu Inferior Fixo */
        .nav-bar { position: fixed; bottom: 0; width: 100%; max-width: 700px; background: white; border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: repeat(5, 1fr); padding: 10px 0; text-align: center; z-index: 1000; }
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
                <a href="painel.php" class="back-btn">←</a>
                <div class="header-title">Minhas Metas</div>
            </div>
            <div class="summary-row">
                <div class="balance-period-box">
                    <div class="balance-period-label">Total Poupado</div>
                    <div class="balance-period-value">R$ <?= number_format($total_poupado, 2, ',', '.') ?></div>
                </div>
                <div class="balance-period-box">
                    <div class="balance-period-label">Saldo Livre</div>
                    <div class="balance-period-value available">R$ <?= number_format($saldo_disponivel, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="alert-box <?= $tipo_msg === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <div class="content-body">
            <?php if ($resultado_metas && $resultado_metas->num_rows > 0): ?>
                <?php while($meta = $resultado_metas->fetch_assoc()): 
                    $alvo = (float)$meta['valor_alvo'];
                    $atual = (float)$meta['valor_atual'];
                    $porcentagem = ($alvo > 0) ? min(100, round(($atual / $alvo) * 100)) : 0;
                    $faltam = max(0, $alvo - $atual);
                    $concluida = ($atual >= $alvo);
                    $prazo_formatado = date('d/m/Y', strtotime($meta['prazo']));
                ?>
                    <div class="goal-card <?= $concluida ? 'completed' : '' ?>">
                        <div class="goal-top">
                            <div class="goal-info-left">
                                <div class="goal-icon"><?= htmlspecialchars($meta['icone']) ?></div>
                                <div>
                                    <div class="goal-title"><?= htmlspecialchars($meta['titulo']) ?></div>
                                    <div class="goal-date">Prazo: <?= $prazo_formatado ?></div>
                                </div>
                            </div>
                            <div class="goal-actions-top">
                                <?php if ($concluida): ?>
                                    <div class="goal-badge completed">Concluída ✓</div>
                                <?php else: ?>
                                    <div class="goal-badge"><?= $porcentagem ?>%</div>
                                <?php endif; ?>

                                <!-- Botão para excluir e devolver valor ao saldo -->
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir esta meta e devolver o valor guardado ao seu saldo?');">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="meta_id" value="<?= $meta['id'] ?>">
                                    <button type="submit" class="btn-delete-mini" title="Excluir meta">🗑️</button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="goal-progress-bar">
                            <div class="goal-progress-fill" style="width: <?= $porcentagem ?>%;"></div>
                        </div>
                        
                        <div class="goal-footer">
                            <span>R$ <?= number_format($atual, 2, ',', '.') ?> de R$ <?= number_format($alvo, 2, ',', '.') ?></span>
                            <span>Faltam R$ <?= number_format($faltam, 2, ',', '.') ?></span>
                        </div>

                        <?php if ($concluida): ?>
                            <form method="POST">
                                <input type="hidden" name="acao" value="resgatar">
                                <input type="hidden" name="meta_id" value="<?= $meta['id'] ?>">
                                <button type="submit" class="btn-redeem">🎉 Resgatar R$ <?= number_format($atual, 2, ',', '.') ?> para usar</button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn-deposit" onclick="abrirModalDeposito(<?= $meta['id'] ?>, '<?= htmlspecialchars(addslashes($meta['titulo'])) ?>', <?= $faltam ?>)">
                                + Guardar dinheiro na meta
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; color: #64748b; margin: 40px 0 20px 0; font-size: 14px;">Você ainda não tem metas criadas. Crie a primeira agora!</p>
            <?php endif; ?>

            <button type="button" class="btn-new-goal" onclick="abrirModalMeta()">+ Nova meta de poupança</button>
        </div>
        <!-- Menu Inferior Fixo -->
        <nav class="nav-bar">
            <a href="painel.php" class="nav-item"><span>🏠</span>Início</a>
            <a href="educacao-financeira.php" class="nav-item"><span>📖</span>Educação</a>
            <a href="extrato.php" class="nav-item"><span>📄</span>Extrato</a>
            <a href="metas.php" class="nav-item active"><span>🎯</span>Metas</a>
            <a href="perfil.php" class="nav-item"><span>👤</span>Perfil</a>
        </nav>
    </div>

    <!-- Modal Nova Meta -->
    <div class="modal-overlay" id="modalNovaMeta">
        <div class="modal-card">
            <div class="modal-title">Criar Nova Meta</div>
            <form method="POST">
                <input type="hidden" name="acao" value="nova_meta">
                <div class="form-group">
                    <label class="form-label">O que você quer conquistar?</label>
                    <input type="text" name="titulo" class="form-input" placeholder="Ex: Fone Bluetooth, Livro..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Escolha um ícone rápido</label>
                    <div class="quick-chips">
                        <button type="button" class="chip-btn selected" onclick="selecionarIcone(this, '🎯')">🎯</button>
                        <button type="button" class="chip-btn" onclick="selecionarIcone(this, '📖')">📖</button>
                        <button type="button" class="chip-btn" onclick="selecionarIcone(this, '🎧')">🎧</button>
                        <button type="button" class="chip-btn" onclick="selecionarIcone(this, '🎮')">🎮</button>
                        <button type="button" class="chip-btn" onclick="selecionarIcone(this, '👟')">👟</button>
                        <button type="button" class="chip-btn" onclick="selecionarIcone(this, '🎁')">🎁</button>
                    </div>
                    <input type="hidden" name="icone" id="inputIcone" value="🎯">
                </div>
                <div class="form-group">
                    <label class="form-label">Valor Alvo (R$)</label>
                    <input type="number" step="0.01" min="1" name="valor_alvo" class="form-input" placeholder="0,00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Prazo Limite</label>
                    <input type="date" name="prazo" class="form-input" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="fecharModalMeta()">Cancelar</button>
                    <button type="submit" class="btn-save">Criar Meta</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Depositar -->
    <div class="modal-overlay" id="modalDeposito">
        <div class="modal-card">
            <div class="modal-title" id="tituloModalDep">Depositar na Meta</div>
            <form method="POST">
                <input type="hidden" name="acao" value="depositar">
                <input type="hidden" name="meta_id" id="inputMetaId">
                <div class="form-group">
                    <label class="form-label">Atalhos de valor (Saldo livre: R$ <?= number_format($saldo_disponivel, 2, ',', '.') ?>)</label>
                    <div class="quick-chips">
                        <button type="button" class="chip-btn" onclick="definirValorDep(2.50)">+R$ 2,50</button>
                        <button type="button" class="chip-btn" onclick="definirValorDep(5.00)">+R$ 5,00</button>
                        <button type="button" class="chip-btn" onclick="definirValorDep(10.00)">+R$ 10,00</button>
                        <button type="button" class="chip-btn" id="btnCompletarMeta" onclick="definirValorDep(0)">Completar</button>
                    </div>
                    <input type="number" step="0.01" min="0.01" max="<?= $saldo_disponivel ?>" name="valor_deposito" id="inputValorDeposito" class="form-input" placeholder="0,00" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="fecharModalDeposito()">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let valorFaltanteAtual = 0;

        function abrirModalMeta() {
            document.getElementById('modalNovaMeta').style.display = 'flex';
        }
        function fecharModalMeta() {
            document.getElementById('modalNovaMeta').style.display = 'none';
        }
        function selecionarIcone(botao, emoji) {
            document.querySelectorAll('#modalNovaMeta .chip-btn').forEach(b => b.classList.remove('selected'));
            botao.classList.add('selected');
            document.getElementById('inputIcone').value = emoji;
        }

        function abrirModalDeposito(id, nome, faltam) {
            valorFaltanteAtual = faltam;
            document.getElementById('inputMetaId').value = id;
            document.getElementById('tituloModalDep').innerText = 'Guardar em: ' + nome;
            document.getElementById('inputValorDeposito').value = '';
            document.getElementById('btnCompletarMeta').onclick = function() { definirValorDep(faltam); };
            document.getElementById('modalDeposito').style.display = 'flex';
        }
        function fecharModalDeposito() {
            document.getElementById('modalDeposito').style.display = 'none';
        }
        function definirValorDep(valor) {
            document.getElementById('inputValorDeposito').value = valor.toFixed(2);
        }

        // Fecha modais ao clicar fora
        window.addEventListener('click', function(e) {
            if (e.target.id === 'modalNovaMeta') fecharModalMeta();
            if (e.target.id === 'modalDeposito') fecharModalDeposito();
        });
    </script>
</body>
</html>
<?php $conexao->close(); ?>
