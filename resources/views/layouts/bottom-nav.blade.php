{{-- Barra de navegação inferior — só aparece no mobile (md:hidden). Dá acesso de
     1 toque aos 4 destinos mais usados no dia a dia; o resto continua no drawer
     (hambúrguer da topbar). "Buscar" dispara o evento nonna-open-search, que a
     busca global da topbar escuta (ver layouts/app.blade.php). --}}
<nav class="bottom-nav md:hidden" aria-label="Navegação principal">
    <a href="{{ route('dashboard') }}"
       class="bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <x-icon name="house" size="19" />
        <span>Início</span>
    </a>
    <a href="{{ route('meetings.index') }}"
       class="bottom-nav-item {{ request()->routeIs('meetings.*') ? 'active' : '' }}">
        <x-icon name="calendar" size="19" />
        <span>Agenda</span>
    </a>
    <a href="{{ route('fila.index') }}"
       class="bottom-nav-item {{ request()->routeIs('fila.*') ? 'active' : '' }}">
        <x-icon name="list-checks" size="19" />
        <span>Filas</span>
    </a>
    <a href="{{ route('approvals.index') }}"
       class="bottom-nav-item {{ request()->routeIs('approvals.*') ? 'active' : '' }}">
        <x-icon name="circle-check" size="19" />
        <span>Aprovações</span>
    </a>
    <button type="button"
            onclick="window.dispatchEvent(new CustomEvent('nonna-open-search'))"
            class="bottom-nav-item">
        <x-icon name="search" size="19" />
        <span>Buscar</span>
    </button>
</nav>
