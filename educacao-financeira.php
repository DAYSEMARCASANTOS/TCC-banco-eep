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
$mensagem_bonus = "";
$tipo_alerta = "";

// Lista dos 5 módulos educativos com conteúdo e quiz de 2 perguntas cada
$modulos = [
    1 => [
        'icone' => '💵',
        'titulo' => 'O que é dinheiro?',
        'desc' => 'Entenda como o dinheiro surgiu, seu valor e como ele circula no dia a dia.',
        'tempo' => '3 min • +R$ 2,50 de bônus',
        'conteudo' => 'O dinheiro é um meio de troca que substituiu o escambo (troca direta de mercadorias). Saber quanto tempo de trabalho ou esforço custa cada item ajuda você a dar mais valor antes de gastar.',
        'perguntas' => [
            [
                'enunciado' => 'Qual é a principal função do dinheiro no dia a dia?',
                'opcoes' => ['Servir como meio de troca e reserva de valor', 'Ser gasto totalmente assim que recebido', 'Substituir a necessidade de planejamento'],
                'correta' => 0
            ],
            [
                'enunciado' => 'O que existia antes da invenção do dinheiro?',
                'opcoes' => ['Cartão de crédito', 'Escambo (troca direta de produtos)', 'Pix'],
                'correta' => 1
            ]
        ]
    ],
    2 => [
        'icone' => '📊',
        'titulo' => 'Necessidade vs. Desejo',
        'desc' => 'Aprenda a diferenciar o que você realmente precisa do que é apenas impulso.',
        'tempo' => '4 min • +R$ 2,50 de bônus',
        'conteudo' => 'Necessidade é aquilo essencial para sua saúde, estudo e alimentação (ex: lanche nutritivo, material escolar). Desejo é algo supérfluo ou por impulso (ex: comprar doces todo dia só porque os amigos compraram).',
        'perguntas' => [
            [
                'enunciado' => 'Qual destes exemplos representa uma NECESSIDADE escolar?',
                'opcoes' => ['Comprar figurinhas repetidas', 'Comprar um caderno para as aulas', 'Comprar refrigerante todos os dias por impulso'],
                'correta' => 1
            ],
            [
                'enunciado' => 'O que devemos priorizar ao receber nossa mesada?',
                'opcoes' => ['Os desejos imediatos', 'As necessidades básicas e metas', 'Gastar tudo no primeiro dia'],
                'correta' => 1
            ]
        ]
    ],
    3 => [
        'icone' => '🎯',
        'titulo' => 'Como criar metas financeiras',
        'desc' => 'Defina objetivos claros e descubra quanto guardar por semana para alcançá-los.',
        'tempo' => '4 min • +R$ 2,50 de bônus',
        'conteudo' => 'Uma boa meta tem valor e prazo definidos. Se você quer comprar um fone de R$ 60,00 em 6 semanas, precisa guardar R$ 10,00 por semana — priorizando guardar antes de gastar.',
        'perguntas' => [
            [
                'enunciado' => 'Qual é o segredo para alcançar uma meta financeira mais rápido?',
                'opcoes' => ['Gastar tudo e guardar só se sobrar no fim do mês', 'Separar o valor da meta assim que receber o dinheiro', 'Não definir prazo nem valor'],
                'correta' => 1
            ],
            [
                'enunciado' => 'Para juntar R$ 60,00 em 6 semanas, quanto você precisa guardar por semana?',
                'opcoes' => ['R$ 5,00', 'R$ 10,00', 'R$ 15,00'],
                'correta' => 1
            ]
        ]
    ],
    4 => [
        'icone' => '🛡️',
        'titulo' => 'Controle de limite diário',
        'desc' => 'Use o limite diário do Banco EEP para fazer sua mesada durar o mês inteiro.',
        'tempo' => '3 min • +R$ 2,50 de bônus',
        'conteudo' => 'Dividir seu saldo pelos dias letivos do mês evita que você fique sem dinheiro na última semana. O limite diário protege você contra compras por impulso na cantina.',
        'perguntas' => [
            [
                'enunciado' => 'Para que serve o limite diário no Banco EEP?',
                'opcoes' => ['Para impedir que você gaste todo o saldo em um único dia', 'Para diminuir seu saldo automaticamente', 'Para bloquear o acesso à conta'],
                'correta' => 0
            ],
            [
                'enunciado' => 'Como calcular um bom limite diário?',
                'opcoes' => ['Dividindo o saldo pelos dias que você vai usar o dinheiro', 'Colocando o valor máximo possível', 'Gastando sem olhar o saldo'],
                'correta' => 0
            ]
        ]
    ],
    5 => [
        'icone' => '📈',
        'titulo' => 'O poder de poupar cedo',
        'desc' => 'Veja como pequenos valores guardados hoje se transformam em grandes conquistas.',
        'tempo' => '5 min • +R$ 2,50 de bônus',
        'conteudo' => 'Guardar R$ 2,00 por dia de aula parece pouco, mas em 200 dias letivos você acumula R$ 400,00! O hábito de poupar é mais importante do que a quantia inicial.',
        'perguntas' => [
            [
                'enunciado' => 'Quanto você acumula se guardar R$ 2,00 durante 100 dias?',
                'opcoes' => ['R$ 20,00', 'R$ 100,00', 'R$ 200,00'],
                'correta' => 2
            ],
            [
                'enunciado' => 'O que é mais importante quando começamos a poupar?',
                'opcoes' => ['Começar apenas quando tiver muito dinheiro', 'Criar o hábito constante de guardar', 'Nunca gastar com nada'],
                'correta' => 1
            ]
        ]
    ]
];
// Processa a conclusão do Quiz enviado pelo formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['concluir_modulo'])) {
    $mod_id = (int) $_POST['modulo_id'];
    $respostas_usuario = $_POST['respostas'] ?? [];

    if (isset($modulos[$mod_id])) {
        $todas_corretas = true;
        $perguntas_mod = $modulos[$mod_id]['perguntas'];

        // Valida todas as perguntas do módulo
        foreach ($perguntas_mod as $idx => $pergunta) {
            if (!isset($respostas_usuario[$idx]) || (int)$respostas_usuario[$idx] !== $pergunta['correta']) {
                $todas_corretas = false;
                break;
            }
        }

        if ($todas_corretas) {
            // Verifica se já concluiu antes
            $check = $conexao->prepare("SELECT id FROM progresso_educacao WHERE ra = ? AND modulo_id = ?");
            $check->bind_param("si", $ra_logado, $mod_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows === 0) {
                $ins = $conexao->prepare("INSERT INTO progresso_educacao (ra, modulo_id) VALUES (?, ?)");
                $ins->bind_param("si", $ra_logado, $mod_id);
                $ins->execute();
                $ins->close();

                // Credita R$ 2,50 no saldo do estudante
                $upd = $conexao->prepare("UPDATE estudantes SET saldo = saldo + 2.50 WHERE ra = ?");
                $upd->bind_param("s", $ra_logado);
                $upd->execute();
                $upd->close();

                $mensagem_bonus = "🎉 Parabéns! Você acertou tudo, concluiu o módulo e ganhou +R$ 2,50 no saldo!";
                $tipo_alerta = "success";
            } else {
                $mensagem_bonus = "✔ Revisão concluída com 100% de acerto! (Bônus já resgatado anteriormente).";
                $tipo_alerta = "success";
            }
            $check->close();
        } else {
            $mensagem_bonus = "❌ Ops! Você errou alguma questão. Leia o texto do módulo com atenção e tente novamente!";
            $tipo_alerta = "error";
        }
    }
}

// Busca módulos já concluídos (com Prepared Statement seguro)
$concluidos = [];
$stmt = $conexao->prepare("SELECT modulo_id FROM progresso_educacao WHERE ra = ?");
$stmt->bind_param("s", $ra_logado);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $concluidos[] = (int) $row['modulo_id'];
}
$stmt->close();

$total_modulos = count($modulos);
$qtd_concluidos = count($concluidos);
$porcentagem = $total_modulos > 0 ? round(($qtd_concluidos / $total_modulos) * 100) : 0;
$proximo_liberado = $qtd_concluidos + 1;

$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Educação Financeira</title>
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f1f5f9; min-height: 100vh; color: #1e293b; display: flex; justify-content: center; }
        
        .app-container { 
            width: 100%; max-width: 700px; background: #fff; min-height: 100vh; 
            display: flex; flex-direction: column; position: relative; padding-bottom: 90px; 
            box-shadow: 0 0 25px rgba(0,0,0,0.08);
        }

        /* Topo Laranja */
        .header-banner {
            background: linear-gradient(135deg, #ea580c, #f97316);
            color: white; padding: 24px 20px;
            border-bottom-left-radius: 28px; border-bottom-right-radius: 28px;
            display: flex; flex-direction: column; gap: 16px;
        }
        .header-top-row { display: flex; align-items: center; gap: 14px; }
        .back-btn {
            background: rgba(255, 255, 255, 0.2); width: 36px; height: 36px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 18px; text-decoration: none;
        }
        .header-title { font-size: 18px; font-weight: 700; }

        .progress-card-row {
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(255, 255, 255, 0.15); padding: 16px; border-radius: 16px;
        }
        .progress-label { font-size: 11px; opacity: 0.9; margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.5px; }
        .progress-val { font-size: 20px; font-weight: 700; }
        .progress-circle {
            width: 54px; height: 54px; border: 4px solid rgba(255,255,255,0.4);
            border-top-color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
        }

        /* Alerta de feedback */
        .alert-box { margin: 16px 20px 0 20px; padding: 14px; border-radius: 12px; font-size: 13px; font-weight: 600; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Corpo */
        .content-body { padding: 20px; display: flex; flex-direction: column; gap: 16px; }
        .section-heading { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Módulos Cards */
        .module-card {
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px;
            display: flex; flex-direction: column; gap: 12px; transition: 0.2s;
        }
        .module-card.completed { border-color: #22c55e; background: #f0fdf4; }
        .module-card.locked { opacity: 0.65; background: #f8fafc; }

        .module-top { display: flex; align-items: flex-start; gap: 14px; }
        .module-icon {
            width: 48px; height: 48px; background: #fff7ed; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
        }
        .module-info { flex: 1; }
        .module-title-row { display: flex; justify-content: space-between; align-items: center; }
        .module-name { font-size: 15px; font-weight: 700; color: #0f172a; }
        .module-desc { font-size: 12px; color: #64748b; line-height: 1.4; margin-top: 4px; }
        .module-meta { font-size: 11px; color: #ea580c; font-weight: 600; margin-top: 6px; }

        .btn-continue {
            background: #f97316; color: white; border: none; padding: 12px; border-radius: 12px;
            font-size: 14px; font-weight: 600; cursor: pointer; text-align: center; width: 100%;
        }
        .btn-continue:hover { background: #ea580c; }
        .btn-review { background: #16a34a; }
        .btn-review:hover { background: #15803d; }
        .btn-locked { background: #cbd5e1; color: #64748b; cursor: not-allowed; }

        /* Modal da Aula + Quiz */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65);
            z-index: 2000; justify-content: center; align-items: center; padding: 16px;
            overflow-y: auto;
        }
        .modal-card {
            background: white; width: 100%; max-width: 520px; border-radius: 20px; padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); animation: fadeIn 0.2s ease;
            max-height: 90vh; overflow-y: auto;
        }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .modal-title { font-size: 18px; font-weight: 700; color: #0f172a; }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
        
        .lesson-text {
            font-size: 14px; line-height: 1.6; color: #334155; background: #f8fafc;
            padding: 14px; border-radius: 12px; margin-bottom: 18px; border-left: 4px solid #f97316;
        }

        .quiz-block { margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
        .quiz-question { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 10px; }
        .quiz-option {
            display: flex; align-items: center; gap: 10px; padding: 11px 12px; border: 1px solid #cbd5e1;
            border-radius: 10px; margin-bottom: 8px; cursor: pointer; font-size: 13px; transition: 0.15s;
        }
        .quiz-option:hover { border-color: #f97316; background: #fff7ed; }
        .quiz-option input[type="radio"] { accent-color: #ea580c; width: 16px; height: 16px; }

        /* Menu Inferior Fixo */
        .nav-bar {
            position: fixed; bottom: 0; width: 100%; max-width: 700px; background: white;
            border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: repeat(5, 1fr);
            padding: 10px 0; text-align: center; z-index: 1000;
        }
        .nav-item { display: flex; flex-direction: column; align-items: center; gap: 4px; text-decoration: none; color: #94a3b8; font-size: 11px; font-weight: 500; }
        .nav-item.active { color: #ea580c; font-weight: 700; }
        .nav-item span { font-size: 20px; }
    </style>
</head>
<body>

<div class="app-container">
    <div class="header-banner">
        <div class="header-top-row">
            <a href="painel.php" class="back-btn">←</a>
            <div class="header-title">Educação Financeira • EEP</div>
        </div>
        <div class="progress-card-row">
            <div>
                <div class="progress-label">Seu Progresso Real</div>
                <div class="progress-val"><?= $qtd_concluidos ?> / <?= $total_modulos ?> módulos</div>
            </div>
            <div class="progress-circle"><?= $porcentagem ?>%</div>
        </div>
    </div>

    <?php if (!empty($mensagem_bonus)): ?>
        <div class="alert-box <?= $tipo_alerta === 'success' ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($mensagem_bonus) ?>
        </div>
    <?php endif; ?>

    <div class="content-body">
        <div class="section-heading">Trilha de Aprendizado</div>

        <?php foreach ($modulos as $id => $mod): 
            $concluido = in_array($id, $concluidos);
            $liberado = ($id <= $proximo_liberado);
            $classe_card = $concluido ? 'completed' : (!$liberado ? 'locked' : '');
        ?>
            <div class="module-card <?= $classe_card ?>">
                <div class="module-top">
                    <div class="module-icon"><?= $mod['icone'] ?></div>
                    <div class="module-info">
                        <div class="module-title-row">
                            <span class="module-name"><?= $id ?>. <?= htmlspecialchars($mod['titulo']) ?></span>
                            <?php if ($concluido): ?>
                                <span style="font-size: 12px; font-weight: 700; color: #16a34a;">✔ Concluído</span>
                            <?php elseif (!$liberado): ?>
                                <span>🔒</span>
                            <?php endif; ?>
                        </div>
                        <div class="module-desc"><?= htmlspecialchars($mod['desc']) ?></div>
                        <div class="module-meta"><?= htmlspecialchars($mod['tempo']) ?></div>
                    </div>
                </div>

                <?php if ($concluido): ?>
                    <button type="button" class="btn-continue btn-review" onclick="abrirModulo(<?= $id ?>)">
                        Revisar Conteúdo e Refazer Quiz
                    </button>
                <?php elseif ($liberado): ?>
                    <button type="button" class="btn-continue" onclick="abrirModulo(<?= $id ?>)">
                        Estudar e Ganhar Bônus (+R$ 2,50)
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-continue btn-locked" disabled>
                        Conclua o módulo anterior para liberar
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <nav class="nav-bar">
        <a href="painel.php" class="nav-item"><span>🏠</span>Início</a>
        <a href="educacao-financeira.php" class="nav-item active"><span>📖</span>Educação</a>
        <a href="extrato.php" class="nav-item"><span>📄</span>Extrato</a>
        <a href="metas.php" class="nav-item"><span>🎯</span>Metas</a>
        <a href="perfil.php" class="nav-item"><span>👤</span>Perfil</a>
    </nav>
</div>

<div class="modal-overlay" id="modalAula">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Título do Módulo</div>
            <button type="button" class="close-modal" onclick="fecharModulo()">×</button>
        </div>

        <div class="lesson-text" id="modalConteudo">Texto da aula...</div>

        <form method="POST" action="educacao-financeira.php">
            <input type="hidden" name="modulo_id" id="inputModuloId" value="">
            <input type="hidden" name="concluir_modulo" value="1">
            
            <div id="containerPerguntas"></div>

            <button type="submit" class="btn-continue" style="margin-top: 8px;">
                Enviar Respostas e Concluir Módulo
            </button>
        </form>
    </div>
</div>

<script>
const dadosModulos = <?= json_encode($modulos, JSON_UNESCAPED_UNICODE) ?>;

function abrirModulo(id) {
    const mod = dadosModulos[id];
    if (!mod) return;

    document.getElementById('modalTitulo').innerText = mod.icone + ' ' + mod.titulo;
    document.getElementById('modalConteudo').innerText = mod.conteudo;
    document.getElementById('inputModuloId').value = id;

    const container = document.getElementById('containerPerguntas');
    container.innerHTML = '';

    mod.perguntas.forEach((perg, pIndex) => {
        let opcoesHTML = '';
        perg.opcoes.forEach((textoOpcao, oIndex) => {
            opcoesHTML += `
                <label class="quiz-option">
                    <input type="radio" name="respostas[${pIndex}]" value="${oIndex}" required>
                    <span>${textoOpcao}</span>
                </label>
            `;
        });

        container.innerHTML += `
            <div class="quiz-block">
                <div class="quiz-question">Pergunta ${pIndex + 1}: ${perg.enunciado}</div>
                ${opcoesHTML}
            </div>
        `;
    });

    document.getElementById('modalAula').style.display = 'flex';
}

function fecharModulo() {
    document.getElementById('modalAula').style.display = 'none';
}

document.getElementById('modalAula').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModulo();
    }
});
</script>

</body>
</html>

