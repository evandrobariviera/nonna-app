<!-- ==================== GRUPO 1: CADASTROS ==================== -->
<div x-data="{ open: {{ request()->is('cadastros/*') ? 'true' : 'false' }} }" class="space-y-1">
    <button @click="open = !open" class="group flex w-full items-center justify-between rounded-lg p-3 text-sm font-semibold leading-6 text-gray-700 dark:text-gray-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
        <span class="flex items-center gap-x-3">
            <!-- Ícone: Database / Cadastros -->
            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75m16.5 0v3.75m-16.5-3.75v3.75" />
            </svg>
            Cadastros
        </span>
        <!-- Seta de rotação -->
        <svg class="h-4 w-4 transform transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    
    <!-- Submenus de Cadastros -->
    <ul x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="pl-9 space-y-1" style="display: none;">
        <li>
            <a href="{{ route('dashboard') }}" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold {{ request()->routeIs('dashboard') ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50/20' : 'text-gray-600 dark:text-gray-400 hover:text-indigo-600' }}">Clientes</a>
        </li>
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Contatos</a>
        </li>
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Contas de Anúncios</a>
        </li>
    </ul>
</div>

<!-- ==================== GRUPO 2: OPERACIONAL ==================== -->
<div x-data="{ open: {{ request()->is('operacional/*') ? 'true' : 'false' }} }" class="space-y-1 mt-2">
    <button @click="open = !open" class="group flex w-full items-center justify-between rounded-lg p-3 text-sm font-semibold leading-6 text-gray-700 dark:text-gray-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
        <span class="flex items-center gap-x-3">
            <!-- Ícone: Briefcase / Maleta -->
            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 .621-.504 1.125-1.125 1.125H4.875c-.621 0-1.125-.504-1.125-1.125v-4.25m16.5 0a3 3 0 00-1.875-2.783m-12.75 0a3 3 0 00-1.875 2.783m16.5 0l-3.31-9.93a3 3 0 00-2.847-2.05H6.621a3 3 0 00-2.847 2.05L.47 14.15M16.5 10.5h-9" />
            </svg>
            Operacional
        </span>
        <svg class="h-4 w-4 transform transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    
    <!-- Submenus de Operacional -->
    <ul x-show="open" x-transition class="pl-9 space-y-1" style="display: none;">
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Planejamentos</a>
        </li>
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Projetos</a>
        </li>
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Agenda (ATAs)</a>
        </li>
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Tickets / Suporte</a>
        </li>
    </ul>
</div>

<!-- ==================== GRUPO 3: INTELIGÊNCIA IA ==================== -->
<div x-data="{ open: {{ request()->is('ia/*') ? 'true' : 'false' }} }" class="space-y-1 mt-2">
    <button @click="open = !open" class="group flex w-full items-center justify-between rounded-lg p-3 text-sm font-semibold leading-6 text-gray-700 dark:text-gray-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
        <span class="flex items-center gap-x-3">
            <!-- Ícone: Sparkles / Estrelas -->
            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l5.096-.813a8.985 8.985 0 003.078-1.3l2.368-2.367a8.985 8.985 0 00-3.078-1.3l-2.368 2.368zm-5.625-1.583C2.868 12.923 2 11.06 2 9a9 9 0 1118 0c0 2.06-.868 3.923-2.188 5.321H4.188z" />
            </svg>
            Inteligência IA
        </span>
        <svg class="h-4 w-4 transform transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    
    <!-- Submenus de IA -->
    <ul x-show="open" x-transition class="pl-9 space-y-1" style="display: none;">
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Redator Inteligente</a>
        </li>
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Criador de Imagem</a>
        </li>
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Meus Agentes (RAG)</a>
        </li>
    </ul>
</div>

<!-- ==================== GRUPO 4: RELATÓRIOS & ANALYTICS ==================== -->
<div x-data="{ open: {{ request()->is('relatorios/*') ? 'true' : 'false' }} }" class="space-y-1 mt-2">
    <button @click="open = !open" class="group flex w-full items-center justify-between rounded-lg p-3 text-sm font-semibold leading-6 text-gray-700 dark:text-gray-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
        <span class="flex items-center gap-x-3">
            <!-- Ícone: Chart / Gráficos -->
            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v5.625c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 013 18.75v-5.625zM16.5 13.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 12 21 12.504 21 13.125v5.625c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125v-5.625zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v10.125c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625z" />
            </svg>
            Relatórios
        </span>
        <svg class="h-4 w-4 transform transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
    
    <!-- Submenus de Relatórios -->
    <ul x-show="open" x-transition class="pl-9 space-y-1" style="display: none;">
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Produtividade (SLA)</a>
        </li>
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Carga de Projetos</a>
        </li>
        <li>
            <a href="#" class="block rounded-md py-2 pr-2 pl-4 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600">Performance (Ads ROI)</a>
        </li>
    </ul>
</div>