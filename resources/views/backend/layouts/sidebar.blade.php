@php $route = request()->route()->getName(); @endphp
<div class="sidebar">
    <div class="sidebar-user-panel">
        <div class="user-avatar"><i class="fas fa-user-tie"></i></div>
        <div>
            <span class="user-info-name">{{ auth()->user()->name }}</span>
            <span class="user-info-role">{{ auth()->user()->getRoleNames()->first() ?? 'Персонал' }}</span>
        </div>
    </div>
    <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

            @can('dashboard_view')
            <li class="nav-item">
                <a href="{{ route('backend.admin.dashboard') }}" class="nav-link {{ $route === 'backend.admin.dashboard' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-chart-pie"></i><p>Дашборд</p>
                </a>
            </li>
            @endcan

            @can('sale_create')
            <li class="nav-item">
                <a href="{{ route('backend.admin.cart.index') }}" class="nav-link {{ $route === 'backend.admin.cart.index' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-cash-register"></i>
                    <p>Касса <span class="badge badge-pill" style="background:var(--dk-primary);font-size:9px;margin-left:4px;">POS</span></p>
                </a>
            </li>
            @endcan

            @if(auth()->user()->hasAnyPermission(['customer_create','customer_view','customer_update','customer_delete','customer_sales','supplier_create','supplier_view','supplier_update','supplier_delete']))
            <li class="nav-item {{ request()->routeIs(['backend.admin.customers.*','backend.admin.suppliers.*']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.customers.*','backend.admin.suppliers.*']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-users"></i><p>Контакты <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @if(auth()->user()->hasAnyPermission(['customer_create','customer_view','customer_update','customer_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.customers.index') }}" class="nav-link {{ request()->routeIs(['backend.admin.customers.*']) ? 'active' : '' }}">
                            <i class="fas fa-user nav-icon"></i><p>Покупатели</p>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->hasAnyPermission(['supplier_create','supplier_view','supplier_update','supplier_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.suppliers.index') }}" class="nav-link {{ request()->routeIs(['backend.admin.suppliers.*']) ? 'active' : '' }}">
                            <i class="fas fa-truck nav-icon"></i><p>Поставщики</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif

            @if(auth()->user()->hasAnyPermission(['product_create','product_view','product_update','product_delete','product_import','product_purchase']))
            <li class="nav-item {{ request()->routeIs(['backend.admin.products.*','backend.admin.brands.*','backend.admin.categories.*','backend.admin.units.*']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.products.*','backend.admin.brands.*','backend.admin.categories.*','backend.admin.units.*']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-hamburger"></i><p>Меню / Товары <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @if(auth()->user()->hasAnyPermission(['product_view','product_update','product_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.products.index') }}" class="nav-link {{ request()->routeIs(['backend.admin.products.index','backend.admin.products.edit']) ? 'active' : '' }}">
                            <i class="fas fa-list nav-icon"></i><p>Все товары</p>
                        </a>
                    </li>
                    @endif
                    @can('product_create')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.products.create') }}" class="nav-link {{ request()->routeIs('backend.admin.products.create') ? 'active' : '' }}">
                            <i class="fas fa-plus-circle nav-icon"></i><p>Добавить товар</p>
                        </a>
                    </li>
                    @endcan
                    @can('product_import')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.products.import') }}" class="nav-link {{ request()->routeIs('backend.admin.products.import') ? 'active' : '' }}">
                            <i class="fas fa-file-import nav-icon"></i><p>Импорт</p>
                        </a>
                    </li>
                    @endcan
                    @if(auth()->user()->hasAnyPermission(['category_create','category_view','category_update','category_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.categories.index') }}" class="nav-link {{ request()->routeIs(['backend.admin.categories.*']) ? 'active' : '' }}">
                            <i class="fas fa-tag nav-icon"></i><p>Категории</p>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->hasAnyPermission(['brand_create','brand_view','brand_update','brand_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.brands.index') }}" class="nav-link {{ request()->routeIs(['backend.admin.brands.*']) ? 'active' : '' }}">
                            <i class="fas fa-award nav-icon"></i><p>Бренды</p>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->hasAnyPermission(['unit_create','unit_view','unit_update','unit_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.units.index') }}" class="nav-link {{ request()->routeIs(['backend.admin.units.*']) ? 'active' : '' }}">
                            <i class="fas fa-balance-scale nav-icon"></i><p>Единицы измерения</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif

            @if(auth()->user()->hasAnyPermission(['sale_view']))
            <li class="nav-item {{ request()->routeIs(['backend.admin.orders.*']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.orders.*']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-receipt"></i><p>Заказы <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('sale_view')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.orders.index') }}" class="nav-link {{ request()->routeIs('backend.admin.orders.index') ? 'active' : '' }}">
                            <i class="fas fa-list-alt nav-icon"></i><p>Все заказы</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endif

            @if(auth()->user()->hasAnyPermission(['purchase_create','purchase_view','purchase_update','purchase_delete']))
            <li class="nav-item {{ request()->routeIs(['backend.admin.purchase.*']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.purchase.*']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-box-open"></i><p>Закупки <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('purchase_view')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.purchase.index') }}" class="nav-link {{ request()->routeIs('backend.admin.purchase.index') ? 'active' : '' }}">
                            <i class="fas fa-list nav-icon"></i><p>Список закупок</p>
                        </a>
                    </li>
                    @endcan
                    @can('purchase_create')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.purchase.create') }}" class="nav-link {{ request()->routeIs('backend.admin.purchase.create') ? 'active' : '' }}">
                            <i class="fas fa-plus-circle nav-icon"></i><p>Новая закупка</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endif

            <li class="nav-item {{ request()->routeIs(['backend.admin.ingredients.*']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.ingredients.*']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-flask"></i><p>Ингредиенты <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.ingredients.index') }}" class="nav-link {{ request()->routeIs('backend.admin.ingredients.index') ? 'active' : '' }}">
                            <i class="fas fa-list nav-icon"></i><p>Список</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.ingredients.create') }}" class="nav-link {{ request()->routeIs('backend.admin.ingredients.create') ? 'active' : '' }}">
                            <i class="fas fa-plus-circle nav-icon"></i><p>Добавить</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.ingredients.report') }}" class="nav-link {{ request()->routeIs('backend.admin.ingredients.report') ? 'active' : '' }}">
                            <i class="fas fa-chart-bar nav-icon"></i><p>Остатки</p>
                        </a>
                    </li>
                </ul>
            </li>

            @if(auth()->user()->hasAnyPermission(['reports_summary','reports_sales','reports_inventory']))
            <li class="nav-item {{ request()->routeIs(['backend.admin.sale.report','backend.admin.sale.summery','backend.admin.inventory.report']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.sale.report','backend.admin.sale.summery','backend.admin.inventory.report']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-chart-bar"></i><p>Отчёты <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('reports_summary')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.sale.summery') }}" class="nav-link {{ request()->routeIs('backend.admin.sale.summery') ? 'active' : '' }}">
                            <i class="fas fa-file-alt nav-icon"></i><p>Сводка продаж</p>
                        </a>
                    </li>
                    @endcan
                    @can('reports_sales')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.sale.report') }}" class="nav-link {{ request()->routeIs('backend.admin.sale.report') ? 'active' : '' }}">
                            <i class="fas fa-chart-line nav-icon"></i><p>Детализация</p>
                        </a>
                    </li>
                    @endcan
                    @can('reports_inventory')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.inventory.report') }}" class="nav-link {{ request()->routeIs('backend.admin.inventory.report') ? 'active' : '' }}">
                            <i class="fas fa-boxes nav-icon"></i><p>Склад</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endif

            @if(auth()->user()->hasAnyPermission(['currency_create','currency_view','role_create','role_view','permission_view','user_create','user_view','website_settings']))
            <li class="nav-header">АДМИНИСТРИРОВАНИЕ</li>
            <li class="nav-item {{ request()->routeIs(['backend.admin.settings.*','backend.admin.currencies.*','backend.admin.roles','backend.admin.permissions','backend.admin.users']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.settings.*','backend.admin.currencies.*','backend.admin.roles','backend.admin.permissions','backend.admin.users']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-cog"></i><p>Настройки <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @if(auth()->user()->hasAnyPermission(['website_settings','contact_settings','socials_settings','style_settings','custom_settings','notification_settings','website_status_settings','invoice_settings']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.settings.website.general') }}?active-tab=website-info" class="nav-link {{ request()->routeIs('backend.admin.settings.website.general') ? 'active' : '' }}">
                            <i class="fas fa-sliders-h nav-icon"></i><p>Общие настройки</p>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->hasAnyPermission(['currency_create','currency_view','currency_update','currency_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.currencies.index') }}" class="nav-link {{ request()->routeIs(['backend.admin.currencies.*']) ? 'active' : '' }}">
                            <i class="fas fa-coins nav-icon"></i><p>Валюта</p>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->hasAnyPermission(['role_create','role_view','permission_view']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.roles') }}" class="nav-link {{ request()->routeIs('backend.admin.roles') ? 'active' : '' }}">
                            <i class="fas fa-shield-alt nav-icon"></i><p>Роли и права</p>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->hasAnyPermission(['user_create','user_view','user_update','user_delete','user_suspend']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.users') }}" class="nav-link {{ request()->routeIs('backend.admin.users') ? 'active' : '' }}">
                            <i class="fas fa-user-cog nav-icon"></i><p>Пользователи</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif

        </ul>
    </nav>
</div>
<script>
document.querySelectorAll('.nav-treeview').forEach(el => {
    if (el.querySelector('.nav-link.active')) {
        const parent = el.closest('.nav-item');
        if (parent) { parent.classList.add('menu-open'); const link = parent.querySelector(':scope > .nav-link'); if (link) link.classList.add('active'); }
    }
});
</script>
