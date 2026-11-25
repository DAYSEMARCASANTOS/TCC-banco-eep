<?php
session_start();

// Encerra a sessão de verdade ao clicar em Sair
if (isset($_GET['sair'])) {
    session_destroy();
    header("Location: login-estudante.php");
    exit;
}

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

// Garante que a coluna 'foto' exista na tabela estudantes
$check_col = $conexao->query("SHOW COLUMNS FROM estudantes LIKE 'foto'");
if ($check_col && $check_col->num_rows === 0) {
    $conexao->query("ALTER TABLE estudantes ADD COLUMN foto VARCHAR(255) DEFAULT NULL");
}

// Cria tabelas de segurança e suporte automaticamente
$conexao->query("CREATE TABLE IF NOT EXISTS seguranca_conta (
    ra VARCHAR(20) PRIMARY KEY,
    cartao_status ENUM('ativo', 'cancelado') DEFAULT 'ativo',
    transacoes_travadas TINYINT(1) DEFAULT 0,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conexao->query("CREATE TABLE IF NOT EXISTS suporte_chamados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ra VARCHAR(20) NOT NULL,
    assunto VARCHAR(100) NOT NULL,
    descricao TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'Aberto',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conexao->query("INSERT IGNORE INTO seguranca_conta (ra) VALUES ('$ra_logado')");

// Processar ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 0. Atualizar Foto do Perfil / Cartão (Galeria ou Câmera)
    if ($acao === 'atualizar_foto') {
        $novo_caminho = null;
        $pasta_uploads = "uploads/";
        if (!is_dir($pasta_uploads)) {
            mkdir($pasta_uploads, 0777, true);
        }

        if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['nova_foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $destino = $pasta_uploads . "aluno_" . preg_replace('/\D/', '', $ra_logado) . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES['nova_foto']['tmp_name'], $destino)) {
                    $novo_caminho = $destino;
                }
            }
        } elseif (!empty($_POST['foto_camera'])) {
            $dados_img = $_POST['foto_camera'];
            if (preg_match('/^data:image\/(\w+);base64,/', $dados_img)) {
                $dados_img = substr($dados_img, strpos($dados_img, ',') + 1);
                $dados_binarios = base64_decode($dados_img);
                if ($dados_binarios !== false) {
                    $destino = $pasta_uploads . "aluno_" . preg_replace('/\D/', '', $ra_logado) . "_" . time() . ".jpg";
                    if (file_put_contents($destino, $dados_binarios)) {
                        $novo_caminho = $destino;
                    }
                }
            }
        }

        if ($novo_caminho) {
            $stmt_f = $conexao->prepare("UPDATE estudantes SET foto = ? WHERE ra = ?");
            $stmt_f->bind_param("ss", $novo_caminho, $ra_logado);
            if ($stmt_f->execute()) {
                $mensagem = "📸 Foto atualizada! Ela já aparece no seu perfil e no cartão.";
                $tipo_msg = "success";
            }
            $stmt_f->close();
        } else {
            $mensagem = "⚠ Selecione uma imagem válida ou tire uma foto pela câmera.";
            $tipo_msg = "error";
        }
    }

    // 1. Alterar Senha
    if ($acao === 'alterar_senha') {
        $nova_senha = trim($_POST['nova_senha'] ?? '');
        if (strlen($nova_senha) >= 4) {
            $stmt = $conexao->prepare("UPDATE estudantes SET senha = ? WHERE ra = ?");
            $stmt->bind_param("ss", $nova_senha, $ra_logado);
            if ($stmt->execute()) {
                $mensagem = "✔ Senha alterada com sucesso!";
                $tipo_msg = "success";
            }
            $stmt->close();
        } else {
            $mensagem = "⚠ A senha deve ter pelo menos 4 caracteres.";
            $tipo_msg = "error";
        }
    }

    // 2. Travar / Destravar Transações
    if ($acao === 'toggle_transacoes') {
        $travar = isset($_POST['travar']) ? 1 : 0;
        $conexao->query("UPDATE seguranca_conta SET transacoes_travadas = $travar WHERE ra = '$ra_logado'");
        if ($travar === 1) {
            $mensagem = "🔒 Transações bloqueadas! Nenhuma compra ou saída poderá ser feita.";
            $tipo_msg = "error";
        } else {
            $mensagem = "🔓 Transações liberadas normalmente na sua conta.";
            $tipo_msg = "success";
        }
    }

    // 3. Cancelar Cartão por Perda ou Roubo
    if ($acao === 'cancelar_cartao') {
        $conexao->query("UPDATE seguranca_conta SET cartao_status = 'cancelado', transacoes_travadas = 1 WHERE ra = '$ra_logado'");
        $mensagem = "🚨 Cartão cancelado por segurança e transações travadas! Procure a secretaria ou solicite 2ª via no suporte.";
        $tipo_msg = "error";
    }

    // 4. Emitir Novo Cartão Virtual
    if ($acao === 'novo_cartao') {
        $conexao->query("UPDATE seguranca_conta SET cartao_status = 'ativo', transacoes_travadas = 0 WHERE ra = '$ra_logado'");
        $mensagem = "💳 Novo cartão virtual EEP emitido e ativado com sucesso!";
        $tipo_msg = "success";
    }

    // 5. Enviar Chamado de Suporte
    if ($acao === 'enviar_suporte') {
        $assunto = trim($_POST['assunto'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        if (!empty($assunto) && !empty($descricao)) {
            $stmt = $conexao->prepare("INSERT INTO suporte_chamados (ra, assunto, descricao) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $ra_logado, $assunto, $descricao);
            if ($stmt->execute()) {
                $mensagem = "🎧 Chamado registrado! A equipe do Banco EEP responderá em breve.";
                $tipo_msg = "success";
            }
            $stmt->close();
        }
    }
}

// Busca os dados do estudante
$res_est = $conexao->query("SELECT * FROM estudantes WHERE ra = '$ra_logado'");
$estudante = $res_est ? $res_est->fetch_assoc() : [];

// Busca status de segurança atual
$res_seg = $conexao->query("SELECT * FROM seguranca_conta WHERE ra = '$ra_logado'");
$seguranca = $res_seg ? $res_seg->fetch_assoc() : ['cartao_status' => 'ativo', 'transacoes_travadas' => 0];
$cartao_cancelado = ($seguranca['cartao_status'] === 'cancelado');
$transacoes_travadas = ((int)$seguranca['transacoes_travadas'] === 1);

$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Perfil e Segurança</title>
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f1f5f9; min-height: 100vh; color: #1e293b; display: flex; justify-content: center; }
        
        .app-container { 
            width: 100%; max-width: 700px; background: #fff; min-height: 100vh; 
            display: flex; flex-direction: column; position: relative; padding-bottom: 95px; 
            box-shadow: 0 0 25px rgba(0,0,0,0.08);
        }
        
        /* Topo Azul do Perfil com Foto */
        .header-card { background: linear-gradient(135deg, #082258, #1d4ed8); color: white; padding: 28px 20px; border-bottom-left-radius: 28px; border-bottom-right-radius: 28px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .header-left { display: flex; align-items: center; gap: 16px; }
        .profile-avatar { 
            width: 68px; height: 68px; background: rgba(255,255,255,0.2); border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; font-size: 30px; 
            flex-shrink: 0; overflow: hidden; border: 3px solid rgba(255,255,255,0.75);
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-header-info { display: flex; flex-direction: column; gap: 3px; }
        .profile-name { font-size: 18px; font-weight: 700; }
        .profile-class { font-size: 13px; opacity: 0.9; }
        .btn-edit-photo {
            background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4);
            padding: 8px 12px; border-radius: 10px; font-size: 12px; font-weight: 600;
            cursor: pointer; white-space: nowrap; transition: 0.2s;
        }
        .btn-edit-photo:hover { background: rgba(255,255,255,0.3); }

        /* Alertas */
        .alert-box { margin: 16px 20px 0 20px; padding: 14px; border-radius: 12px; font-size: 13px; font-weight: 600; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Corpo de Conteúdo */
        .content-body { padding: 20px; flex: 1; }
        
        /* Cartões de Seção */
        .section-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .section-card.danger-zone { border-color: #fecaca; background: #fffafa; }
        .section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; }
        .section-title.danger { color: #b91c1c; }

        /* Badges de Status */
        .status-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-blocked { background: #fee2e2; color: #b91c1c; }

        /* Campos de Informação */
        .info-group { margin-bottom: 12px; }
        .info-group:last-child { margin-bottom: 0; }
        .info-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .info-value { font-size: 14px; font-weight: 600; color: #1e293b; padding: 6px 0; border-bottom: 1px solid #f1f5f9; }

        /* Inputs e Botões */
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px; outline: none; background: #f8fafc; margin-bottom: 12px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #2563eb; background: #fff; }
        
        .btn-primary { width: 100%; background: #082258; color: white; border: none; padding: 12px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: #1d4ed8; }

        .btn-danger { width: 100%; background: #dc2626; color: white; border: none; padding: 13px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 8px; }
        .btn-danger:hover { background: #b91c1c; }

        .btn-success { width: 100%; background: #16a34a; color: white; border: none; padding: 13px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 8px; }
        .btn-success:hover { background: #15803d; }

        .btn-outline { width: 100%; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 12px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-outline:hover { background: #dbeafe; }

        /* Toggles */
        .toggle-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; gap: 12px; }
        .toggle-item:last-child { border-bottom: none; }
        .toggle-text-title { font-size: 13px; font-weight: 600; color: #0f172a; }
        .toggle-text-desc { font-size: 11px; color: #64748b; margin-top: 2px; line-height: 1.4; }
        
        .switch { position: relative; display: inline-block; width: 46px; height: 26px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 26px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: #dc2626; }
        input:checked + .slider:before { transform: translateX(20px); }

        /* FAQ Acordeão do Suporte */
        .faq-item { border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 8px; overflow: hidden; }
        .faq-question { width: 100%; text-align: left; background: #f8fafc; border: none; padding: 12px 14px; font-size: 13px; font-weight: 600; color: #1e293b; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .faq-answer { display: none; padding: 12px 14px; font-size: 12px; color: #475569; line-height: 1.5; background: #fff; border-top: 1px solid #f1f5f9; }

        /* Modais */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 2000; justify-content: center; align-items: center; padding: 20px; }
        .modal-card { background: white; width: 100%; max-width: 420px; padding: 24px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-title { font-size: 18px; font-weight: 700; margin-bottom: 10px; color: #0f172a; }
        .modal-desc { font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 16px; }
        .modal-buttons { display: flex; gap: 10px; }
        .btn-cancel { flex: 1; background: #f1f5f9; color: #64748b; border: none; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer; }

        /* Prévia de Foto no Modal */
        .photo-preview-modal {
            width: 96px; height: 96px; border-radius: 50%; background: #e2e8f0;
            margin: 0 auto 14px auto; overflow: hidden; border: 3px solid #1d4ed8;
            display: flex; align-items: center; justify-content: center; font-size: 36px;
        }
        .photo-preview-modal img { width: 100%; height: 100%; object-fit: cover; }
        #videoPerfil { width: 100%; border-radius: 12px; background: #000; max-height: 200px; object-fit: cover; margin-bottom: 10px; }

        /* Botão Sair da Conta */
        .btn-logout { width: 100%; background: #fef2f2; border: 1px solid #fee2e2; color: #dc2626; padding: 14px; border-radius: 16px; font-size: 14px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; display: block; margin-top: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #fee2e2; }

        /* Menu Inferior Fixo */
        .nav-bar { position: fixed; bottom: 0; width: 100%; max-width: 700px; background: white; border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: repeat(5, 1fr); padding: 10px 0; text-align: center; z-index: 1000; }
        .nav-item { display: flex; flex-direction: column; align-items: center; gap: 4px; text-decoration: none; color: #94a3b8; font-size: 11px; font-weight: 500; }
        .nav-item.active { color: #2563eb; font-weight: 700; }
        .nav-item span { font-size: 20px; }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Topo Azul do Perfil com Foto -->
        <div class="header-card">
            <div class="header-left">
                <div class="profile-avatar">
                    <?php if (!empty($estudante['foto']) && file_exists($estudante['foto'])): ?>
                        <img src="<?= htmlspecialchars($estudante['foto']) ?>" alt="Foto do Estudante">
                    <?php else: ?>
                        🎒
                    <?php endif; ?>
                </div>
                <div class="profile-header-info">
                    <div class="profile-name"><?= htmlspecialchars($estudante['nome_completo'] ?? 'Estudante EEP') ?></div>
                    <div class="profile-class">RA: <?= htmlspecialchars($estudante['ra'] ?? '') ?> • Banco EEP</div>
                </div>
            </div>
            <button type="button" class="btn-edit-photo" onclick="abrirModalFoto()">📷 Foto</button>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="alert-box <?= $tipo_msg === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <div class="content-body">
            <!-- Seção 1: Dados do Estudante e Responsáveis -->
            <div class="section-card">
                <div class="section-title">👤 Informações da Conta</div>
                
                <div class="info-group">
                    <div class="info-label">Nome Completo</div>
                    <div class="info-value"><?= htmlspecialchars($estudante['nome_completo'] ?? 'Não informado') ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Matrícula (RA)</div>
                    <div class="info-value"><?= htmlspecialchars($estudante['ra'] ?? '') ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Escola</div>
                    <div class="info-value"><?= htmlspecialchars($estudante['escola'] ?? 'Não informada') ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Responsável(is) Vinculado(s)</div>
                    <div class="info-value" style="border-bottom: none;"><?= htmlspecialchars($estudante['responsavel'] ?? 'Não informado') ?></div>
                </div>
            </div>

            <div class="info-item" style="padding: 12px 0;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                RESPONSÁVEL(IS) VINCULADO(S)
            </span>
            <p style="font-size: 15px; font-weight: 700; color: #1e293b; margin-top: 4px;">
                Mariana Oliveira Santos
                <!-- Aqui você pode listar os outros responsáveis vindos do banco -->
            </p>
        </div>

        <button type="button" onclick="abrirFormResponsavel()" 
                style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer;">
            + Adicionar
        </button>
    </div>

    <!-- Formulário oculto que abre ao clicar em "+ Adicionar" -->
    <form id="formNovoResponsavel" action="../actions/adicionar-responsavel-aluno.php" method="POST" 
          style="display: none; margin-top: 14px; background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0;">
        
        <p style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 10px;">
            Vincular Novo Responsável
        </p>

        <div style="margin-bottom: 10px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">
                Nome Completo do Responsável
            </label>
            <input type="text" name="nome_responsavel" placeholder="Ex: Carlos Eduardo Santos" required
                   style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
        </div>

        <div style="margin-bottom: 12px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">
                CPF do Responsável
            </label>
            <input type="text" name="cpf_responsavel" placeholder="000.000.000-00" maxlength="14" oninput="mascaraCPF(this)" required
                   style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" 
                    style="flex: 1; background: #1d4ed8; color: #fff; border: none; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer;">
                Salvar Responsável
            </button>
            <button type="button" onclick="abrirFormResponsavel()" 
                    style="background: #e2e8f0; color: #475569; border: none; padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                Cancelar
            </button>
        </div>
    </form>
</div>

<script>
    function abrirFormResponsavel() {
        const form = document.getElementById('formNovoResponsavel');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }

    function mascaraCPF(campo) {
        let v = campo.value.replace(/\D/g, '');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        campo.value = v;
    }
</script>


            <!-- Seção 2: Proteção de Emergência (Cartão e Transações) -->
            <div class="section-card danger-zone">
                <div class="section-title danger">
                    <span>🛡️ Central de Segurança e Emergência</span>
                    <?php if ($cartao_cancelado): ?>
                        <span class="status-badge badge-blocked">Cartão Cancelado</span>
                    <?php else: ?>
                        <span class="status-badge badge-active">Cartão Ativo</span>
                    <?php endif; ?>
                </div>

                <!-- Switch para Fechar / Travar Transações -->
                <form method="POST" id="formTravarTransacoes">
                    <input type="hidden" name="acao" value="toggle_transacoes">
                    <div class="toggle-item">
                        <div>
                            <div class="toggle-text-title">Fechar / Travar Transações</div>
                            <div class="toggle-text-desc">Bloqueia imediatamente compras na cantina e saídas da conta.</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="travar" value="1" <?= $transacoes_travadas ? 'checked' : '' ?> onchange="document.getElementById('formTravarTransacoes').submit();">
                            <span class="slider"></span>
                        </label>
                    </div>
                </form>

                <!-- Botão Cancelar Cartão (Perda ou Roubo) -->
                <div style="margin-top: 12px;">
                    <?php if (!$cartao_cancelado): ?>
                        <button type="button" class="btn-danger" onclick="abrirModalCancelar()">
                            🚨 Cancelar Cartão (Perda ou Roubo)
                        </button>
                    <?php else: ?>
                        <p style="font-size: 12px; color: #991b1b; margin-bottom: 8px; line-height: 1.4;">
                            Seu cartão anterior foi cancelado permanentemente por segurança.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="acao" value="novo_cartao">
                            <button type="submit" class="btn-success">💳 Emitir Novo Cartão Virtual</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Seção 3: Alterar Senha -->
            <div class="section-card">
                <div class="section-title">🔑 Alterar Senha de Acesso</div>
                <form method="POST">
                    <input type="hidden" name="acao" value="alterar_senha">
                    <div class="info-label" style="margin-bottom: 6px;">Nova senha</div>
                    <input type="password" name="nova_senha" class="form-input" placeholder="Digite a nova senha (mín. 4 dígitos)" required>
                    <button type="submit" class="btn-primary">Salvar Nova Senha</button>
                </form>
            </div>
            <!-- Seção 4: Central de Suporte e Ajuda EEP -->
            <div class="section-card">
                <div class="section-title">🎧 Suporte e Ajuda Banco EEP</div>

                <div class="faq-item">
                    <button type="button" class="faq-question" onclick="toggleFaq(this)">
                        <span>Perdi ou tiveram meu cartão roubado. O que fazer?</span>
                        <span>▾</span>
                    </button>
                    <div class="faq-answer">
                        Clique imediatamente no botão vermelho <strong>"Cancelar Cartão (Perda ou Roubo)"</strong> acima. Isso bloqueia seu cartão e trava todas as transações na hora. Depois, solicite um novo cartão virtual ou procure a secretaria.
                    </div>
                </div>

                <div class="faq-item">
                    <button type="button" class="faq-question" onclick="toggleFaq(this)">
                        <span>Como alterar meu limite diário de gastos?</span>
                        <span>▾</span>
                    </button>
                    <div class="faq-answer">
                        Você pode ajustar seu limite diário diretamente na tela inicial (Início) ou pedir para seu responsável atualizar nas configurações de segurança.
                    </div>
                </div>

                <div class="faq-item" style="margin-bottom: 14px;">
                    <button type="button" class="faq-question" onclick="toggleFaq(this)">
                        <span>Horário de atendimento presencial na escola</span>
                        <span>▾</span>
                    </button>
                    <div class="faq-answer">
                        Secretaria / Coordenação EEP: Segunda a Sexta, das 07h30 às 17h00.<br>
                        E-mail de suporte: <strong>suporte@banceep.edu.br</strong>
                    </div>
                </div>

                <button type="button" class="btn-outline" onclick="abrirModalSuporte()">
                    💬 Falar com o Suporte / Abrir Chamado
                </button>
            </div>

            <!-- Botão Sair da Conta -->
            <a href="perfil.php?sair=1" class="btn-logout">🚪 Sair da conta</a>
        </div>

        <!-- Menu Inferior Fixo -->
        <nav class="nav-bar">
        <a href="painel.php" class="nav-item"><span>🏠</span>Início</a>
            <a href="educacao-financeira.php" class="nav-item"><span>📖</span>Educação</a>
            <a href="extrato.php" class="nav-item"><span>📄</span>Extrato</a>
            <a href="metas.php" class="nav-item"><span>🎯</span>Metas</a>
            <a href="perfil.php" class="nav-item active"><span>👤</span>Perfil</a>
        </nav>
    </div>

    <!-- Modal 1: Atualizar Foto (Galeria ou Câmera) -->
    <div class="modal-overlay" id="modalFoto">
        <div class="modal-card">
            <div class="modal-title">📸 Atualizar Foto do Perfil e Cartão</div>
            <p class="modal-desc">Escolha uma foto da galeria/Drive ou tire uma foto do seu rosto agora.</p>

            <div class="photo-preview-modal" id="previewModalFoto">
                <?php if (!empty($estudante['foto']) && file_exists($estudante['foto'])): ?>
                    <img src="<?= htmlspecialchars($estudante['foto']) ?>" alt="Foto atual">
                <?php else: ?>
                    🧑
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="atualizar_foto">
                <input type="file" id="inputGaleriaPerfil" name="nova_foto" accept="image/*" style="display: none;">
                <input type="hidden" id="fotoCameraPerfil" name="foto_camera">

                <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                    <button type="button" class="btn-outline" style="flex:1; font-size: 12px;" onclick="document.getElementById('inputGaleriaPerfil').click()">
                        📁 Galeria / Arquivos
                    </button>
                    <button type="button" class="btn-primary" style="flex:1; font-size: 12px;" onclick="abrirCameraPerfil()">
                        📷 Tirar Foto Agora
                    </button>
                </div>

                <!-- Área da Câmera ao Vivo -->
                <div id="areaCameraPerfil" style="display: none; margin-bottom: 12px; text-align: center;">
                    <video id="videoPerfil" autoplay playsinline></video>
                    <canvas id="canvasPerfil" style="display: none;"></canvas>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn-cancel" onclick="fecharCameraPerfil()">Fechar Câmera</button>
                        <button type="button" class="btn-primary" style="flex:1;" onclick="capturarFotoPerfil()">📸 Capturar</button>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="fecharModalFoto()">Cancelar</button>
                    <button type="submit" class="btn-success" style="margin-top:0; flex:1;">Salvar Foto</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Modal 2: Confirmar Cancelamento de Cartão -->
    <div class="modal-overlay" id="modalCancelarCartao">
        <div class="modal-card">
            <div class="modal-title" style="color: #dc2626;">🚨 Cancelar Cartão por Perda/Roubo?</div>
            <p class="modal-desc">
                Esta ação <strong>cancelará seu cartão atual permanentemente</strong> e bloqueará todas as transações da conta por segurança. Tem certeza que deseja continuar?
            </p>
            <form method="POST">
                <input type="hidden" name="acao" value="cancelar_cartao">
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="fecharModalCancelar()">Voltar</button>
                    <button type="submit" class="btn-danger" style="margin-top:0; flex:1;">Sim, Cancelar Agora</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 3: Abrir Chamado de Suporte -->
    <div class="modal-overlay" id="modalSuporte">
        <div class="modal-card">
            <div class="modal-title">🎧 Abrir Chamado de Suporte</div>
            <p class="modal-desc">Descreva o que aconteceu para a equipe do Banco EEP e a coordenação escolar ajudarem você.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="enviar_suporte">
                
                <div class="info-label" style="margin-bottom: 6px;">Motivo do contato</div>
                <select name="assunto" class="form-select" required>
                    <option value="">Selecione um assunto...</option>
                    <option value="Perda ou Roubo de Cartão">Perda ou Roubo de Cartão</option>
                    <option value="Problema com Compra na Cantina">Problema com Compra na Cantina</option>
                    <option value="Dúvida sobre Saldo ou Bônus">Dúvida sobre Saldo ou Bônus</option>
                    <option value="Alteração de Dados / Responsável">Alteração de Dados / Responsável</option>
                    <option value="Outros">Outros</option>
                </select>

                <div class="info-label" style="margin-bottom: 6px;">Mensagem</div>
                <textarea name="descricao" rows="3" class="form-textarea" placeholder="Explique os detalhes aqui..." required></textarea>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="fecharModalSuporte()">Cancelar</button>
                    <button type="submit" class="btn-primary" style="flex:1;">Enviar Chamado</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let streamPerfil = null;

        function toggleFaq(botao) {
            const resposta = botao.nextElementSibling;
            const aberta = resposta.style.display === 'block';
            document.querySelectorAll('.faq-answer').forEach(el => el.style.display = 'none');
            resposta.style.display = aberta ? 'none' : 'block';
        }

        function abrirModalFoto() {
            document.getElementById('modalFoto').style.display = 'flex';
        }
        function fecharModalFoto() {
            fecharCameraPerfil();
            document.getElementById('modalFoto').style.display = 'none';
        }

        document.getElementById('inputGaleriaPerfil').addEventListener('change', function(e) {
            const arquivo = e.target.files[0];
            if (arquivo) {
                document.getElementById('fotoCameraPerfil').value = '';
                const leitor = new FileReader();
                leitor.onload = function(ev) {
                    document.getElementById('previewModalFoto').innerHTML = `<img src="${ev.target.result}" alt="Prévia">`;
                };
                leitor.readAsDataURL(arquivo);
            }
        });

        async function abrirCameraPerfil() {
            const area = document.getElementById('areaCameraPerfil');
            const video = document.getElementById('videoPerfil');
            try {
                streamPerfil = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
                video.srcObject = streamPerfil;
                area.style.display = 'block';
            } catch (err) {
                alert("Não foi possível acessar a câmera. Use a opção 'Galeria / Arquivos'.");
            }
        }

        function fecharCameraPerfil() {
            if (streamPerfil) {
                streamPerfil.getTracks().forEach(track => track.stop());
                streamPerfil = null;
            }
            document.getElementById('areaCameraPerfil').style.display = 'none';
        }

        function capturarFotoPerfil() {
            const video = document.getElementById('videoPerfil');
            const canvas = document.getElementById('canvasPerfil');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
            document.getElementById('fotoCameraPerfil').value = dataUrl;
            document.getElementById('inputGaleriaPerfil').value = '';
            document.getElementById('previewModalFoto').innerHTML = `<img src="${dataUrl}" alt="Foto capturada">`;
            fecharCameraPerfil();
        }

        function abrirModalCancelar() {
            document.getElementById('modalCancelarCartao').style.display = 'flex';
        }
        function fecharModalCancelar() {
            document.getElementById('modalCancelarCartao').style.display = 'none';
        }

        function abrirModalSuporte() {
            document.getElementById('modalSuporte').style.display = 'flex';
        }
        function fecharModalSuporte() {
            document.getElementById('modalSuporte').style.display = 'none';
        }

        window.addEventListener('click', function(e) {
            if (e.target.id === 'modalFoto') fecharModalFoto();
            if (e.target.id === 'modalCancelarCartao') fecharModalCancelar();
            if (e.target.id === 'modalSuporte') fecharModalSuporte();
        });
    </script>
</body>
</html>

