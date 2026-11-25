<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco EEP - Seleção de Perfil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-full flex flex-col justify-between py-10 px-4">

    <div class="max-w-xl mx-auto w-full">
        
        <!-- Voltar para a Home -->
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center text-sm text-slate-500 hover:text-blue-900 transition-colors">
                <i class="fa-solid fa-arrow-left mr-2"></i> Voltar para a página inicial
            </a>
        </div>

        <!-- Cabeçalho com Logotipo -->
        <div class="text-center mb-10">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-white flex items-center justify-center shadow-lg border-2 border-blue-500 overflow-hidden">
            <img src="assets/img/logotipo.jpeg" alt="Logo EEP" class="w-full h-full object-contain scale-125">

            </div>
            <h1 class="text-2xl font-black text-slate-900">Banco EEP</h1>
            <p class="text-xs text-slate-500 mt-1">O banco do ecossistema escolar</p>
            
            <div class="mt-6">
                <span class="text-[11px] font-bold tracking-widest text-slate-400 uppercase">Selecione seu perfil</span>
            </div>
        </div>

        <!-- Lista de Perfis (Caminhos relativos corrigidos) -->
        <div class="space-y-4">
            
            <!-- Estudante -->
            <a href="estudante/login-estudante.php" class="block bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-400 transition-all duration-300 group">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-900 font-bold flex items-center justify-center text-xl shadow-inner">E</div>
                        <div>
                            <h2 class="font-bold text-slate-800 text-base">Estudante</h2>
                            <p class="text-slate-500 text-xs mt-0.5">Consulte saldo e faça pagamentos</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-blue-600 group-hover:translate-x-1 transition-all"></i>
                </div>
            </a>

            <!-- Responsável / Pai -->
<a href="responsavel/login-responsavel.php" class="block bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-emerald-400 transition-all duration-300 group">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 font-bold flex items-center justify-center text-xl shadow-inner">R</div>
            <div>
                <h2 class="font-bold text-slate-800 text-base">Responsável</h2>
                <p class="text-slate-500 text-xs mt-0.5">Monitore e controle os gastos</p>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-emerald-600 group-hover:translate-x-1 transition-all"></i>
    </div>
</a>


            <!-- Funcionários -->
            <a href="login-professor.php" class="block bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-purple-400 transition-all duration-300 group">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-700 font-bold flex items-center justify-center text-xl shadow-inner">F</div>
                        <div>
                            <h2 class="font-bold text-slate-800 text-base">Funcionários</h2>
                            <p class="text-slate-500 text-xs mt-0.5">Consulte saldo e faça pagamentos</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-purple-600 group-hover:translate-x-1 transition-all"></i>
                </div>
            </a>

            <!-- Vendedor / Cantina -->
            <a href="login-vendedor.php" class="block bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-orange-400 transition-all duration-300 group">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-xl bg-orange-50 text-orange-700 font-bold flex items-center justify-center text-xl shadow-inner">V</div>
                        <div>
                            <h2 class="font-bold text-slate-800 text-base">Vendedor / Cantina</h2>
                            <p class="text-slate-500 text-xs mt-0.5">Gerencie vendas e produtos</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-orange-600 group-hover:translate-x-1 transition-all"></i>
                </div>
            </a>

            <!-- Escola / Gestão -->
            <a href="login-escola.php" class="block bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-400 transition-all duration-300 group">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-700 font-bold flex items-center justify-center text-xl shadow-inner">G</div>
                        <div>
                            <h2 class="font-bold text-slate-800 text-base">Escola / Gestão</h2>
                            <p class="text-slate-500 text-xs mt-0.5">Administração do ecossistema</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-indigo-600 group-hover:translate-x-1 transition-all"></i>
                </div>
            </a>

        </div>
    </div>

    <footer class="text-center text-xs text-slate-400 mt-10">
        &copy; 2026 Banco EEP • Ecossistema Escolar
    </footer>

</body>
</html>
