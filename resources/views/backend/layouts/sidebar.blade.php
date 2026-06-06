@php
$route = request()->route()->getName();
@endphp
<div class="sidebar">

    <!-- User panel -->
    <div class="sidebar-user-panel">
        <div class="user-avatar">
            <i class="fas fa-user-tie"></i>
        </div>
        <div>
            <span class="user-info-name">{{ auth()->user()->name }}</span>
            <span class="user-info-role">{{ auth()->user()->getRoleNames()->first() ?? 'Staff' }}</span>
        </div>
    </div>

    <!-- Sidebar Menu -->
    <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

            @can('dashboard_view')
            <li class="nav-item">
                <a href="{{ route('backend.admin.dashboard') }}"
                    class="nav-link {{ $route === 'backend.admin.dashboard' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <p>Dashboard</p>
                </a>
            </li>
            @endcan

            @can('sale_create')
            <li class="nav-item">
                <a href="{{ route('backend.admin.cart.index') }}"
                    class="nav-link {{ $route === 'backend.admin.cart.index' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-cash-register"></i>
                    <p>New Order <span class="badge badge-pill" style="background:var(--rest-primary);font-size:9px;margin-left:4px;">POS</span></p>
                </a>
            </li>
            @endcan

            {{-- People --}}
            @if (auth()->user()->hasAnyPermission([
                'customer_create','customer_view','customer_update','customer_delete','customer_sales',
                'supplier_create','supplier_view','supplier_update','supplier_delete',
            ]))
            <li class="nav-item {{ request()->routeIs(['backend.admin.customers.*','backend.admin.suppliers.*']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.customers.*','backend.admin.suppliers.*']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-users"></i>
                    <p>People <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @if (auth()->user()->hasAnyPermission(['customer_create','customer_view','customer_update','customer_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.customers.index') }}"
                            class="nav-link {{ request()->routeIs(['backend.admin.customers.*']) ? 'active' : '' }}">
                            <i class="fas fa-user nav-icon"></i>
                            <p>Customers</p>
                        </a>
                    </li>
                    @endif
                    @if (auth()->user()->hasAnyPermission(['supplier_create','supplier_view','supplier_update','supplier_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.suppliers.index') }}"
                            class="nav-link {{ request()->routeIs(['backend.admin.suppliers.*']) ? 'active' : '' }}">
                            <i class="fas fa-truck nav-icon"></i>
                            <p>Suppliers</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif

            {{-- Menu / Products --}}
            @if (auth()->user()->hasAnyPermission([
                'product_create','product_view','product_update','product_delete','product_import','product_purchase',
            ]))
            <li class="nav-item {{ request()->routeIs(['backend.admin.products.*','backend.admin.brands.*','backend.admin.categories.*','backend.admin.units.*']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.products.*','backend.admin.brands.*','backend.admin.categories.*','backend.admin.units.*']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-hamburger"></i>
                    <p>Menu / Products <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @if (auth()->user()->hasAnyPermission(['product_view','product_update','product_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.products.index') }}"
                            class="nav-link {{ request()->routeIs(['backend.admin.products.index','backend.admin.products.edit']) ? 'active' : '' }}">
                            <i class="fas fa-list nav-icon"></i>
                            <p>Item List</p>
                        </a>
                    </li>
                    @endif
                    @can('product_create')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.products.create') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.products.create') ? 'active' : '' }}">
                            <i class="fas fa-plus-circle nav-icon"></i>
                            <p>Add Item</p>
                        </a>
                    </li>
                    @endcan
                    @can('product_import')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.products.import') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.products.import') ? 'active' : '' }}">
                            <i class="fas fa-file-import nav-icon"></i>
                            <p>Import Items</p>
                        </a>
                    </li>
                    @endcan
                    @if (auth()->user()->hasAnyPermission(['category_create','category_view','category_update','category_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.categories.index') }}"
                            class="nav-link {{ request()->routeIs(['backend.admin.categories.*']) ? 'active' : '' }}">
                            <i class="fas fa-tag nav-icon"></i>
                            <p>Categories</p>
                        </a>
                    </li>
                    @endif
                    @if (auth()->user()->hasAnyPermission(['brand_create','brand_view','brand_update','brand_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.brands.index') }}"
                            class="nav-link {{ request()->routeIs(['backend.admin.brands.*']) ? 'active' : '' }}">
                            <i class="fas fa-award nav-icon"></i>
                            <p>Brands</p>
                        </a>
                    </li>
                    @endif
                    @if (auth()->user()->hasAnyPermission(['unit_create','unit_view','unit_update','unit_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.units.index') }}"
                            class="nav-link {{ request()->routeIs(['backend.admin.units.*']) ? 'active' : '' }}">
                            <i class="fas fa-balance-scale nav-icon"></i>
                            <p>Units</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif

            {{-- Orders / Sales --}}
            @if (auth()->user()->hasAnyPermission(['sale_view']))
            <li class="nav-item {{ request()->routeIs(['backend.admin.orders.*']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.orders.*']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-receipt"></i>
                    <p>Orders <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('sale_view')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.orders.index') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.orders.index') ? 'active' : '' }}">
                            <i class="fas fa-list-alt nav-icon"></i>
                            <p>Order List</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endif

            {{-- Purchase --}}
            @if (auth()->user()->hasAnyPermission(['purchase_create','purchase_view','purchase_update','purchase_delete']))
            <li class="nav-item {{ request()->routeIs(['backend.admin.purchase.*']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.purchase.*']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-box-open"></i>
                    <p>Purchase <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('purchase_view')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.purchase.index') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.purchase.index') ? 'active' : '' }}">
                            <i class="fas fa-list nav-icon"></i>
                            <p>Purchase List</p>
                        </a>
                    </li>
                    @endcan
                    @can('purchase_create')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.purchase.create') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.purchase.create') ? 'active' : '' }}">
                            <i class="fas fa-plus-circle nav-icon"></i>
                            <p>New Purchase</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endif

            {{-- Ingredients --}}
            <li class="nav-item {{ request()->routeIs(['backend.admin.ingredients.*','backend.admin.products.recipes','backend.admin.ingredients.report']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.ingredients.*','backend.admin.products.recipes','backend.admin.ingredients.report']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-flask"></i>
                    <p>Ingredients <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.ingredients.index') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.ingredients.index') ? 'active' : '' }}">
                            <i class="fas fa-list nav-icon"></i>
                            <p>Ingredient List</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.ingredients.create') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.ingredients.create') ? 'active' : '' }}">
                            <i class="fas fa-plus-circle nav-icon"></i>
                            <p>Add Ingredient</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.ingredients.report') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.ingredients.report') ? 'active' : '' }}">
                            <i class="fas fa-chart-bar nav-icon"></i>
                            <p>Stock Report</p>
                        </a>
                    </li>
                </ul>
            </li>

            {{-- Reports --}}
            @if (auth()->user()->hasAnyPermission(['reports_summary','reports_sales','reports_inventory']))
            <li class="nav-item {{ request()->routeIs(['backend.admin.sale.report','backend.admin.sale.summery','backend.admin.inventory.report']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.sale.report','backend.admin.sale.summery','backend.admin.inventory.report']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-chart-bar"></i>
                    <p>Reports <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('reports_summary')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.sale.summery') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.sale.summery') ? 'active' : '' }}">
                            <i class="fas fa-file-alt nav-icon"></i>
                            <p>Sales Summary</p>
                        </a>
                    </li>
                    @endcan
                    @can('reports_sales')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.sale.report') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.sale.report') ? 'active' : '' }}">
                            <i class="fas fa-chart-line nav-icon"></i>
                            <p>Sales Detail</p>
                        </a>
                    </li>
                    @endcan
                    @can('reports_inventory')
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.inventory.report') }}"
                            class="nav-link {{ request()->routeIs('backend.admin.inventory.report') ? 'active' : '' }}">
                            <i class="fas fa-boxes nav-icon"></i>
                            <p>Inventory</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endif

            {{-- Settings --}}
            @if (auth()->user()->hasAnyPermission([
                'currency_create','currency_view','currency_update','currency_delete','currency_set_default',
                'role_create','role_view','role_update','role_delete','permission_view',
                'user_create','user_view','user_update','user_delete','user_suspend',
                'website_settings','contact_settings','socials_settings','style_settings',
                'custom_settings','notification_settings','website_status_settings','invoice_settings',
            ]))
            <li class="nav-header">ADMINISTRATION</li>

            <li class="nav-item {{ request()->routeIs(['backend.admin.settings.*','backend.admin.currencies.*','backend.admin.roles','backend.admin.permissions','backend.admin.users']) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->routeIs(['backend.admin.settings.*','backend.admin.currencies.*','backend.admin.roles','backend.admin.permissions','backend.admin.users']) ? 'active' : '' }}">
                    <i class="nav-icon fas fa-cog"></i>
                    <p>Settings <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @if (auth()->user()->hasAnyPermission([
                        'website_settings','contact_settings','socials_settings','style_settings',
                        'custom_settings','notification_settings','website_status_settings','invoice_settings',
                    ]))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.settings.website.general') }}?active-tab=website-info"
                            class="nav-link {{ $route === 'backend.admin.settings.website.general' ? 'active' : '' }}">
                            <i class="fas fa-sliders-h nav-icon"></i>
                            <p>General Settings</p>
                        </a>
                    </li>
                    @endif
                    @if (auth()->user()->hasAnyPermission(['currency_create','currency_view','currency_update','currency_delete']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.currencies.index') }}"
                            class="nav-link {{ request()->routeIs(['backend.admin.currencies.*']) ? 'active' : '' }}">
                            <i class="fas fa-coins nav-icon"></i>
                            <p>Currency</p>
                        </a>
                    </li>
                    @endif
                    @if (auth()->user()->hasAnyPermission(['role_create','role_view','role_update','role_delete','permission_view']))
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-shield-alt nav-icon"></i>
                            <p>Roles & Permissions <i class="fas fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @can('role_view')
                            <li class="nav-item">
                                <a href="{{ route('backend.admin.roles') }}"
                                    class="nav-link {{ $route === 'backend.admin.roles' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Roles</p>
                                </a>
                            </li>
                            @endcan
                            @can('permission_view')
                            <li class="nav-item">
                                <a href="{{ route('backend.admin.permissions') }}"
                                    class="nav-link {{ $route === 'backend.admin.permissions' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Permissions</p>
                                </a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif
                    @if (auth()->user()->hasAnyPermission(['user_create','user_view','user_update','user_delete','user_suspend']))
                    <li class="nav-item">
                        <a href="{{ route('backend.admin.users') }}"
                            class="nav-link {{ $route === 'backend.admin.users' ? 'active' : '' }}">
                            <i class="fas fa-user-cog nav-icon"></i>
                            <p>User Management</p>
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
    const treeviewElements = document.querySelectorAll('.nav-treeview');
    treeviewElements.forEach(treeviewElement => {
        const navLinkElements = treeviewElement.querySelectorAll('.nav-link.active');
        if (navLinkElements.length > 0) {
            const parentNavItem = treeviewElement.closest('.nav-item');
            if (parentNavItem) {
                parentNavItem.classList.add('menu-open');
                const childNavLink = parentNavItem.querySelector('.nav-link');
                if (childNavLink) childNavLink.classList.add('active');
            }
        }
    });
</script>
