<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EEP - Cadastro de Estudante</title>
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #1e293b; padding: 20px; }
        .login-container { width: 100%; max-width: 450px; padding: 30px; display: flex; flex-direction: column; background: #fff; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        
        .logo-box { 
            background-color: #ffffff; width: 64px; height: 64px; display: flex; 
            justify-content: center; align-items: center; border-radius: 16px; 
            margin: 0 auto 10px auto; border: 2px solid #3b82f6; overflow: hidden;
        }
        .logo-box img { width: 100%; height: 100%; object-fit: contain; transform: scale(1.3); }
        .brand-title { font-size: 22px; font-weight: 900; color: #0f172a; text-align: center; letter-spacing: 1px; }
        .brand-subtitle { font-size: 13px; color: #64748b; text-align: center; margin-bottom: 20px; font-weight: 500; }

        /* Área da Foto de Perfil / Cartão */
        .photo-upload-box {
            display: flex; flex-direction: column; align-items: center; background: #f8fafc;
            border: 1px dashed #cbd5e1; border-radius: 14px; padding: 16px; margin-bottom: 18px;
        }
        .photo-preview {
            width: 88px; height: 88px; border-radius: 50%; background: #e2e8f0;
            display: flex; align-items: center; justify-content: center; font-size: 34px;
            overflow: hidden; border: 3px solid #1e3a8a; margin-bottom: 10px;
        }
        .photo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .photo-buttons { display: flex; gap: 8px; width: 100%; }
        .btn-photo {
            flex: 1; padding: 9px 10px; font-size: 12px; font-weight: 600; border-radius: 8px;
            cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #1e3a8a;
            text-align: center; transition: 0.2s;
        }
        .btn-photo:hover { background: #eff6ff; border-color: #3b82f6; }
        .btn-photo.camera { background: #1e3a8a; color: #fff; border-color: #1e3a8a; }
        .btn-photo.camera:hover { background: #172554; }

        /* Câmera ao vivo no navegador */
        #cameraArea { display: none; width: 100%; margin-top: 10px; text-align: center; }
        #videoCamera { width: 100%; border-radius: 10px; background: #000; max-height: 220px; object-fit: cover; }
        
        .form-group { width: 100%; margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-group input[type="text"],
        .form-group input[type="password"] { width: 100%; padding: 12px 14px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 10px; outline: none; background-color: #fff; transition: 0.2s; }
        .form-group input:focus { border-color: #1e3a8a; box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1); }

        .resp-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
        .btn-add-resp {
            background: #eff6ff; color: #1e3a8a; border: 1px dashed #93c5fd;
            padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-remove-resp {
            background: #fee2e2; color: #dc2626; border: none; width: 40px; height: 42px;
            border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; flex-shrink: 0;
        }

        .btn-entrar { width: 100%; background-color: #1e3a8a; color: white; border: none; padding: 14px; font-size: 15px; font-weight: 600; border-radius: 10px; cursor: pointer; transition: 0.2s; margin-top: 8px; }
        .btn-entrar:hover { background-color: #172554; }
        .register-footer { margin-top: 18px; font-size: 13px; color: #64748b; text-align: center; }
        .register-footer a { color: #1e3a8a; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <form action="processar-cadastro.php" method="POST" enctype="multipart/form-data" class="login-container">
        <div class="logo-box">
            <img src="logotipo.jpeg" alt="Logo EEP">
        </div>
        <h1 class="brand-title">EEP</h1>
        <p class="brand-subtitle">Banco Escolar • Cadastro de Estudante</p>

        <!-- Bloco de Foto do Estudante -->
        <div class="photo-upload-box">
            <div class="photo-preview" id="previewFoto">🧑</div>
            <span style="font-size: 11px; color: #64748b; margin-bottom: 10px; text-align: center;">
                Foto do rosto (aparecerá no seu cartão e perfil)
            </span>

            <input type="file" id="inputGaleria" name="foto" accept="image/*" style="display: none;">
            <input type="hidden" id="fotoBase64" name="foto_camera">

            <div class="photo-buttons">
                <button type="button" class="btn-photo" onclick="document.getElementById('inputGaleria').click()">
                    📁 Galeria / Arquivos
                </button>
                <button type="button" class="btn-photo camera" onclick="abrirCamera()">
                    📷 Tirar Foto Agora
                </button>
            </div>

            <!-- Área da Webcam ao vivo -->
            <div id="cameraArea">
                <video id="videoCamera" autoplay playsinline></video>
                <canvas id="canvasCamera" style="display: none;"></canvas>
                <div style="display: flex; gap: 8px; margin-top: 8px;">
                    <button type="button" class="btn-photo" onclick="fecharCamera()">Cancelar</button>
                    <button type="button" class="btn-photo camera" onclick="capturarFoto()">📸 Capturar Rosto</button>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="nome_completo">Nome Completo</label>
            <input type="text" id="nome_completo" name="nome_completo" placeholder="Digite seu nome completo" required>
        </div>

        <div class="form-group">
            <label for="responsavel_1">Nome do(s) Responsável(is)</label>
            <div id="lista-responsaveis">
                <div class="resp-row">
                    <input type="text" id="responsavel_1" name="responsavel[]" placeholder="Nome do pai, mãe ou responsável 1" required>
                </div>
            </div>
            <button type="button" class="btn-add-resp" id="btnAddResp" onclick="adicionarResponsavel()">
                + Adicionar outro responsável
            </button>
        </div>

        <div class="form-group">
            <label for="escola">Nome da Escola</label>
            <input type="text" id="escola" name="escola" placeholder="Nome da instituição de ensino" required>
        </div>

        <div class="form-group">
            <label for="cpf">CPF</label>
            <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" required>
        </div>

        <div class="form-group">
            <label for="matricula">RA (Matrícula)</label>
            <input type="text" id="matricula" name="matricula" placeholder="Ex: 10 dígitos no total" maxlength="10" required>
        </div>

        <div class="form-group">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" placeholder="Crie uma senha de acesso" required>
        </div>

        <button type="submit" class="btn-entrar">Criar Minha Conta</button>

        <div class="register-footer">
            Já tem uma conta? <a href="login-estudante.php">Faça login aqui</a>
        </div>
        
    </form>
    <script>
        let streamAtivo = null;

        // 1. Pré-visualização ao escolher imagem da Galeria / Drive
        document.getElementById('inputGaleria').addEventListener('change', function(e) {
            const arquivo = e.target.files[0];
            if (arquivo) {
                document.getElementById('fotoBase64').value = '';
                const leitor = new FileReader();
                leitor.onload = function(ev) {
                    document.getElementById('previewFoto').innerHTML = `<img src="${ev.target.result}" alt="Sua foto">`;
                };
                leitor.readAsDataURL(arquivo);
            }
        });

        // 2. Abrir Webcam / Câmera frontal do celular ou notebook
        async function abrirCamera() {
            const area = document.getElementById('cameraArea');
            const video = document.getElementById('videoCamera');
            try {
                streamAtivo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
                video.srcObject = streamAtivo;
                area.style.display = 'block';
            } catch (err) {
                alert("Não foi possível abrir a câmera diretamente. Você pode usar 'Galeria / Arquivos' para escolher ou tirar uma foto pelo celular.");
            }
        }

        function fecharCamera() {
            if (streamAtivo) {
                streamAtivo.getTracks().forEach(track => track.stop());
                streamAtivo = null;
            }
            document.getElementById('cameraArea').style.display = 'none';
        }

        function capturarFoto() {
            const video = document.getElementById('videoCamera');
            const canvas = document.getElementById('canvasCamera');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            const imagemDataUrl = canvas.toDataURL('image/jpeg', 0.85);
            document.getElementById('fotoBase64').value = imagemDataUrl;
            document.getElementById('inputGaleria').value = '';
            document.getElementById('previewFoto').innerHTML = `<img src="${imagemDataUrl}" alt="Foto capturada">`;
            fecharCamera();
        }

        // 3. Múltiplos responsáveis
        const maxResponsaveis = 4;
        function adicionarResponsavel() {
            const lista = document.getElementById('lista-responsaveis');
            const totalAtual = lista.querySelectorAll('.resp-row').length;
            if (totalAtual >= maxResponsaveis) return;

            const novaLinha = document.createElement('div');
            novaLinha.className = 'resp-row';
            novaLinha.innerHTML = `
                <input type="text" name="responsavel[]" placeholder="Nome do responsável ${totalAtual + 1}" required>
                <button type="button" class="btn-remove-resp" title="Remover" onclick="removerResponsavel(this)">×</button>
            `;
            lista.appendChild(novaLinha);
            if (totalAtual + 1 >= maxResponsaveis) document.getElementById('btnAddResp').style.display = 'none';
        }

        function removerResponsavel(botao) {
            botao.parentElement.remove();
            document.getElementById('btnAddResp').style.display = 'inline-flex';
        }

        // 4. Máscara de CPF
        document.getElementById('cpf').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = v;
        });
    </script>
</body>
</html>

