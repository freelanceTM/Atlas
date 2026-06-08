<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                <i class="fas fa-bars"></i>
            </a>
        </li>
    </ul>
    <ul class="navbar-nav ml-auto align-items-center">
        @can('sale_create')
        <li class="nav-item mr-2">
            <a class="nav-link btn bg-gradient-primary text-white px-3" href="{{ route('backend.admin.cart.index') }}" style="border-radius:8px;font-size:13px;font-weight:600">
                <i class="fas fa-cash-register mr-1"></i> Касса
            </a>
        </li>
        @endcan
        <li class="nav-item mr-1">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button" title="Полный экран">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link d-flex align-items-center gap-2" data-toggle="dropdown" href="#" style="gap:8px">
                <div style="width:32px;height:32px;border-radius:50%;background:rgba(91,124,250,.2);border:1.5px solid rgba(91,124,250,.4);display:flex;align-items:center;justify-content:center">
                    <i class="fas fa-user" style="color:#7b9bff;font-size:13px"></i>
                </div>
                <span style="font-size:13px;font-weight:600">{{ auth()->user()->name }}</span>
                <i class="fas fa-angle-down" style="font-size:11px;color:#8b92b8"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right" style="min-width:180px">
                <div style="padding:10px 14px 8px;border-bottom:1px solid var(--dk-border)">
                    <div style="font-size:12px;font-weight:600;color:var(--dk-text)">{{ auth()->user()->name }}</div>
                    <div style="font-size:11px;color:var(--dk-text-muted)">{{ auth()->user()->email }}</div>
                </div>
                <a href="{{ route('backend.admin.profile') }}" class="dropdown-item mt-1">
                    <i class="fas fa-id-card mr-2" style="width:16px"></i> Мой профиль
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ route('logout') }}" class="dropdown-item" style="color:#ff5470">
                    <i class="fas fa-sign-out-alt mr-2" style="width:16px"></i> Выйти
                </a>
            </div>
        </li>
    </ul>
</nav>
