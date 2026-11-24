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

$ra_logado = $_SESSION['ra'];
$saldo_atual = 0.00;

// Usa SELECT * para não dar erro se a coluna de nome tiver outro título
$stmt = $conexao->prepare("SELECT * FROM estudantes WHERE ra = ?");
if ($stmt) {
    $stmt->bind_param("s", $ra_logado);
    $stmt->execute();
    $resultado_estudante = $stmt->get_result();
    $estudante = $resultado_estudante->fetch_assoc();
    $stmt->close();

    if ($estudante && isset($estudante['saldo'])) {
        $saldo_atual = (float)$estudante['saldo'];
    }
}

// Busca transações reais caso exista uma tabela 'transacoes' no banco
$transacoes = [];
$tabela_existe = $conexao->query("SHOW TABLES LIKE 'transacoes'");

if ($tabela_existe && $tabela_existe->num_rows > 0) {
    $stmt_t = $conexao->prepare("SELECT * FROM transacoes WHERE ra = ? ORDER BY id DESC");
    if ($stmt_t) {
        $stmt_t->bind_param("s", $ra_logado);
        $stmt_t->execute();
        $res_t = $stmt_t->get_result();
        while ($row = $res_t->fetch_assoc()) {
            $transacoes[] = $row;
        }
        $stmt_t->close();
    }
}

// Caso a tabela ainda não exista ou esteja vazia, usa exemplos realistas
if (empty($transacoes)) {
    $transacoes = [
        ['descricao' => 'Bônus Educação Financeira', 'categoria' => 'Bônus • Módulo 1', 'valor' => 2.50, 'tipo' => 'entrada', 'data_hora' => date('Y-m-d 11:00:00')],
        ['descricao' => 'Cantina do Seu Jorge', 'categoria' => 'Salgado', 'valor' => 6.00, 'tipo' => 'saida', 'data_hora' => '2026-11-23 10:15:00'],
        ['descricao' => 'Cantina do Seu Jorge', 'categoria' => 'Suco Natural', 'valor' => 4.00, 'tipo' => 'saida', 'data_hora' => '2026-11-23 10:15:00'],
        ['descricao' => 'Recarga – Maria Santos', 'categoria' => 'Mesada', 'valor' => 30.00, 'tipo' => 'entrada', 'data_hora' => '2026-11-22 18:30:00'],
        ['descricao' => 'Papelaria da Dona Maria', 'categoria' => 'Material', 'valor' => 5.00, 'tipo' => 'saida', 'data_hora' => '2026-11-21 14:20:00']
    ];
}

// Calcula totais de entradas e saídas automaticamente
$total_entradas = 0;
$total_saidas = 0;
foreach ($transacoes as $t) {
    if (isset($t['tipo']) && $t['tipo'] === 'entrada') {
        $total_entradas += (float)$t['valor'];
    } else {
        $total_saidas += (float)$t['valor'];
    }
}

$conexao->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Extrato</title>
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f1f5f9; min-height: 100vh; color: #1e293b; display: flex; justify-content: center; }
        
        .app-container { 
            width: 100%; max-width: 700px; background: #fff; min-height: 100vh; 
            display: flex; flex-direction: column; position: relative; padding-bottom: 90px; 
            box-shadow: 0 0 25px rgba(0,0,0,0.08);
        }
        
        /* Topo Azul do Extrato */
        .header-card { background: linear-gradient(135deg,rgb(12, 20, 136),rgb(3, 17, 58)); color: white; padding: 24px 20px 28px 20px; border-bottom-left-radius: 28px; border-bottom-right-radius: 28px; }
        .top-row { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
        .back-btn { background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; font-size: 18px; cursor: pointer; }
        .header-title { font-size: 20px; font-weight: 700; }

        /* Caixa de Saldo Atual */
        .balance-period-box { background: rgba(255, 255, 255, 0.12); padding: 16px; border-radius: 16px; text-align: center; margin-bottom: 12px; }
        .balance-period-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85; font-weight: 600; }
        .balance-period-value { font-size: 28px; font-weight: 700; margin-top: 4px; color: #4ade80; }

        /* Resumo Entradas e Saídas */
        .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .summary-card { background: rgba(255, 255, 255, 0.1); padding: 12px; border-radius: 14px; text-align: center; }
        .summary-label { font-size: 11px; opacity: 0.85; }
        .summary-val-in { font-size: 15px; font-weight: 700; color: #86efac; margin-top: 2px; }
        .summary-val-out { font-size: 15px; font-weight: 700; color: #fca5a5; margin-top: 2px; }

        /* Corpo e Filtros */
        .content-body { padding: 20px; flex: 1; }
        .search-box { position: relative; margin-bottom: 14px; }
        .search-input { width: 100%; padding: 14px 16px 14px 44px; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 14px; background: #fff; outline: none; transition: border-color 0.2s; }
        .search-input:focus { border-color: #2563eb; }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; }

        /* Botões de Filtro */
        .filter-scroll { display: flex; gap: 8px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 4px; }
        .filter-btn { padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; white-space: nowrap; border: none; cursor: pointer; background: #f1f5f9; color: #64748b; transition: 0.2s; }
        .filter-btn.active { background: #082258; color: white; }

        /* Lista de Transações */
        .transaction-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border: 1px solid #f1f5f9; border-radius: 18px; margin-bottom: 12px; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .transaction-left { display: flex; align-items: center; gap: 14px; }
        .transaction-icon { width: 44px; height: 44px; background: #fff1f2; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .transaction-icon.positive { background: #ecfdf5; }
        .transaction-name { font-size: 14px; font-weight: 600; color: #0f172a; }
        .transaction-details { font-size: 12px; color: #64748b; margin-top: 2px; }
        .transaction-value { font-size: 14px; font-weight: 700; }
        .transaction-value.negative { color: #ef4444; }
        .transaction-value.positive { color: #10b981; }

        .empty-state { display: none; text-align: center; padding: 30px 10px; color: #64748b; font-size: 14px; }

        /* Menu Inferior Fixo alinhado ao container */
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
                <div class="header-title">Extrato</div>
            </div>
            <div class="balance-period-box">
                <div class="balance-period-label">Saldo Disponível</div>
                <div class="balance-period-value">R$ <?= number_format($saldo_atual, 2, ',', '.') ?></div>
            </div>
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-label">Entradas</div>
                    <div class="summary-val-in">+R$ <?= number_format($total_entradas, 2, ',', '.') ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Saídas</div>
                    <div class="summary-val-out">-R$ <?= number_format($total_saidas, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <div class="content-body">
            <!-- Barra de Pesquisa -->
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="inputBusca" class="search-input" placeholder="Buscar por local, item ou categoria...">
            </div>

            <!-- Filtros Funcionais -->
            <div class="filter-scroll">
                <button type="button" class="filter-btn active" data-filtro="todos">Todos</button>
                <button type="button" class="filter-btn" data-filtro="entrada">Entradas (+)</button>
                <button type="button" class="filter-btn" data-filtro="saida">Saídas (-)</button>
            </div>

            <!-- Lista Dinâmica de Transações -->
            <div id="listaTransacoes">
                <?php foreach ($transacoes as $t): 
                    $is_entrada = ($t['tipo'] === 'entrada');
                    $classe_tipo = $is_entrada ? 'entrada' : 'saida';
                    $icone = $is_entrada ? '💰' : '🛍️';
                    if (stripos($t['descricao'], 'Bônus') !== false) $icone = '🎓';
                    elseif (stripos($t['descricao'], 'Cantina') !== false) $icone = '🌭';
                    elseif (stripos($t['descricao'], 'Papelaria') !== false) $icone = '✏️';
                    
                    $data_formatada = date('d/m, H:i', strtotime($t['data_hora']));
                    $sinal = $is_entrada ? '+' : '-';
                ?>
                    <div class="transaction-item" data-tipo="<?= $classe_tipo ?>">
    <div class="transaction-left">
        <div class="transaction-icon <?= $is_entrada ? 'positive' : '' ?>" style="overflow: hidden;">
            <?php if (!empty($t['imagem_produto']) && file_exists($t['imagem_produto'])): ?>
                <img src="<?= htmlspecialchars($t['imagem_produto']) ?>" alt="Produto" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <?= $icone ?>
            <?php endif; ?>
        </div>
        <div>
            <div class="transaction-name"><?= htmlspecialchars($t['descricao']) ?></div>
            <div class="transaction-details"><?= htmlspecialchars($t['categoria']) ?> · <?= $data_formatada ?></div>
        </div>
    </div>
    <div class="transaction-value <?= $is_entrada ? 'positive' : 'negative' ?>">
        <?= $sinal ?>R$ <?= number_format((float)$t['valor'], 2, ',', '.') ?>
    </div>
</div>

                <?php endforeach; ?>
            </div>

            <div class="empty-state" id="msgVazio">Nenhuma transação encontrada para esse filtro.</div>
        </div>

        <!-- Menu Inferior Fixo -->
        <nav class="nav-bar">
            <a href="painel.php" class="nav-item"><span>🏠</span>Início</a>
            <a href="educacao-financeira.php" class="nav-item"><span>📖</span>Educação</a>
            <a href="extrato.php" class="nav-item active"><span>📄</span>Extrato</a>
            <a href="metas.php" class="nav-item"><span>🎯</span>Metas</a>
            <a href="perfil.php" class="nav-item"><span>👤</span>Perfil</a>
        </nav>
    </div>

    <script>
        const inputBusca = document.getElementById('inputBusca');
        const botoesFiltro = document.querySelectorAll('.filter-btn');
        const itensTransacao = document.querySelectorAll('.transaction-item');
        const msgVazio = document.getElementById('msgVazio');

        let filtroAtual = 'todos';

        function filtrarLista() {
            const termo = inputBusca.value.toLowerCase().trim();
            let visiveis = 0;

            itensTransacao.forEach(item => {
                const tipoItem = item.getAttribute('data-tipo');
                const textoItem = item.innerText.toLowerCase();

                const bateFiltro = (filtroAtual === 'todos' || tipoItem === filtroAtual);
                const bateBusca = textoItem.includes(termo);

                if (bateFiltro && bateBusca) {
                    item.style.display = 'flex';
                    visiveis++;
                } else {
                    item.style.display = 'none';
                }
            });

            msgVazio.style.display = (visiveis === 0) ? 'block' : 'none';
        }

        botoesFiltro.forEach(btn => {
            btn.addEventListener('click', () => {
                botoesFiltro.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                filtroAtual = btn.getAttribute('data-filtro');
                filtrarLista();
            });
        });

        inputBusca.addEventListener('input', filtrarLista);
    </script>
</body>
</html>
