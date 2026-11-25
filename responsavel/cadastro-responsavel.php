<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EEP - Cadastro de Responsável</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #f4f7f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #1e293b;
        }

        .card-container {
            background: #ffffff;
            width: 100%;
            max-width: 460px;
            border-radius: 20px;
            padding: 32px 28px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo-box {
            width: 64px;
            height: 64px;
            margin: 0 auto 12px;
            border-radius: 16px;
            border: 2px solid #16803d;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 4px;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
        }

        .subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            background-color: #ffffff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #16803d;
            box-shadow: 0 0 0 3px rgba(22, 128, 61, 0.12);
        }

        input::placeholder {
            color: #94a3b8;
        }

        .filhos-section {
            background-color: #f0fdf4;
            border: 1px dashed #86efac;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .filhos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .filhos-header span {
            font-size: 13px;
            font-weight: 700;
            color: #166534;
        }

        .badge-count {
            background: #16803d;
            color: #fff !important;
            font-weight: 700;
            font-size: 11px;
            padding: 2px 10px;
            border-radius: 999px;
        }

        .filho-card {
            background: #ffffff;
            border: 1px solid #dcfce7;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .filho-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .filho-titulo {
            font-size: 12px;
            font-weight: 700;
            color: #15803d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-remover {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }

        .filho-inputs {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .btn-add-filho {
            width: 100%;
            background: #ffffff;
            color: #16803d;
            border: 1.5px dashed #16803d;
            border-radius: 10px;
            padding: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 42px;
            background-color: #f1f5f9;
        }

        .toggle-pass {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #64748b;
        }

        .btn-submit {
            width: 100%;
            background-color: #16803d;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
        }

        .footer-link {
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: #475569;
        }

        .footer-link a {
            color: #16803d;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <div class="card-container">
        <div class="header">
            <div class="logo-box">
                <img src="../assets/img/logotipo.jpeg" alt="Logo EEP" style="width: 70px; height: 70px; object-fit: contain;" >
            </div>
            <h1 class="title">EEP</h1>
            <p class="subtitle">Banco Escolar • Cadastro de Responsável</p>
        </div>

        <form action="../actions/processar-cadastro-responsavel.php" method="POST" id="formCadastro">
            
            <div class="form-group">
                <label for="nome">Seu Nome Completo</label>
                <input type="text" id="nome" name="nome" placeholder="Digite seu nome completo" required>
            </div>

            <div class="form-group">
                <label for="cpf">Seu CPF</label>
                <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" oninput="mascaraCPF(this)" required>
            </div>

            <div class="form-group">
                <label for="telefone">Telefone / WhatsApp</label>
                <input type="tel" id="telefone" name="telefone" placeholder="(11) 99999-9999" maxlength="15" oninput="mascaraTelefone(this)" required>
            </div>

            <div class="filhos-section">
                <div class="filhos-header">
                    <span>Dependentes / Filhos Vinculados</span>
                    <span class="badge-count" id="contadorFilhos">1 filho</span>
                </div>

                <div id="lista-filhos">
                    <div class="filho-card">
                        <div class="filho-card-top">
                            <span class="filho-titulo">Filho(a) #1</span>
                        </div>
                        <div class="filho-inputs">
                            <input type="text" name="filho_nome[]" placeholder="Nome completo do filho(a)" required>
                            <input type="text" name="filho_cpf[]" placeholder="CPF do filho(a): 000.000.000-00" maxlength="14" oninput="mascaraCPF(this)" required>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn-add-filho" onclick="adicionarFilho()">
                    + Adicionar outro filho
                </button>
            </div>

            <div class="form-group">
                <label for="senha">Crie uma Senha</label>
                <div class="password-wrapper">
                    <input type="password" id="senha" name="senha" placeholder="Mínimo 6 caracteres" minlength="6" required>
                    
                </div>
            </div>

            <div class="form-group">
                <label for="confirmar_senha">Confirme sua Senha</label>
                <div class="password-wrapper">
                    <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a senha" minlength="6" required>
               
                </div>
            </div>

            <button type="submit" class="btn-submit">Criar Conta de Responsável</button>

            <div class="footer-link">
                Já tem uma conta? <a href="login-responsavel.php">Faça login aqui</a>
            </div>
        </form>
    </div>
  
