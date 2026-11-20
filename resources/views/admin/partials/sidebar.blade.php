@php
    $adminUser = auth()->user();
    $roleName = $adminUser?->getRoleNames()->first() ?? 'Staff';
    $friendlyRole = $roleName === 'Vendedor B2B' ? 'Vendedor / B2B' : $roleName;

    $roleBadgeClass = match ($roleName) {
        'SuperAdmin' => 'bg-warning text-dark',
        'Administrador' => 'bg-danger text-white',
        'Almacenero' => 'bg-success text-white',
        'Vendedor B2B' => 'bg-info text-dark',
        default => 'bg-secondary text-white',
    };

    $nameParts = preg_split('/\s+/', trim((string) ($adminUser?->name ?? 'G'))) ?: ['G'];
    $initials = mb_strtoupper(
        mb_substr($nameParts[0] ?? 'G', 0, 1) . mb_substr($nameParts[1] ?? '', 0, 1)
    );
@endphp

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="main-sidebar sidebar-dark-primary elevation-4 transition-transform duration-200 ease-out"
>
    {{-- Brand Logo --}}
    <a href="{{ route('admin.dashboard') }}" class="brand-link d-flex align-items-center justify-content-between px-3 py-3">
        <div class="d-flex align-items-center gap-2">
            <span class="d-inline-flex h-9 w-9 align-items-center justify-content-center rounded-circle border border-warning bg-dark font-serif text-lg font-bold text-warning">
                G
            </span>
            <div class="leading-tight">
                <span class="brand-text font-serif text-lg font-bold tracking-widest text-white d-block">
                    GLAMORA
                </span>
                <small class="d-block text-[10px] uppercase tracking-wider text-white-50">
                    AdminLTE · Gestión
                </small>
            </div>
        </div>
    </a>

    {{-- Sidebar --}}
    <div class="sidebar px-2">
        {{-- Sidebar user panel --}}
        <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center border-bottom border-secondary">
            <div class="image">
                <div class="d-flex h-9 w-9 align-items-center justify-content-center rounded-circle bg-secondary font-serif text-xs font-bold text-white">
                    {{ $initials }}
                </div>
            </div>
            <div class="info min-w-0 flex-1">
                <a href="{{ route('account.index') }}" class="d-block text-truncate text-sm font-semibold text-white">
                    {{ $adminUser?->name }}
                </a>
                <div class="mt-1 d-flex flex-wrap align-items-center gap-1">
                    <span class="badge {{ $roleBadgeClass }} text-[10px]">
                        {{ $friendlyRole }}
                    </span>
                    @if ($adminUser?->two_factor_enabled)
                        <span class="badge bg-emerald-600 text-white text-[10px]">2FA</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar Menu --}}
        <nav aria-label="Menú Administrativo" class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                {{-- 1. DASHBOARD PRINCIPAL --}}
                <li class="nav-header">DASHBOARD PRINCIPAL</li>
                <li class="nav-item">
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                    >
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard &amp; KPIs</p>
                    </a>
                </li>

                {{-- 2. CATÁLOGO Y MULTIMEDIA --}}
                @if ($adminUser?->can('catalog.view') || $adminUser?->can('catalog.edit') || $adminUser?->can('marketing.coupons') || $adminUser?->can('marketing.blog'))
                    <li class="nav-header">CATÁLOGO Y MULTIMEDIA</li>
                    @can('catalog.view')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.pim.categories.index') }}"
                                class="nav-link {{ request()->routeIs('admin.pim.categories.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-tags"></i>
                                <p>Categorías</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.pim.brands.index') }}"
                                class="nav-link {{ request()->routeIs('admin.pim.brands.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-award"></i>
                                <p>Marcas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.pim.products.index') }}"
                                class="nav-link {{ request()->routeIs('admin.pim.products.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-spray-can-sparkles"></i>
                                <p>Productos y Tonos</p>
                            </a>
                        </li>
                    @endcan

                    @if ($adminUser?->can('catalog.edit') || $adminUser?->can('marketing.coupons') || $adminUser?->can('marketing.blog'))
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.banners.index') }}"
                                class="nav-link {{ request()->routeIs('admin.banners.*') || request()->routeIs('admin.multimedia.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-images"></i>
                                <p>Banners y Multimedia</p>
                            </a>
                        </li>
                    @endif
                @endif

                {{-- 3. INVENTARIO Y LOTES --}}
                @if ($adminUser?->can('inventory.view') || $adminUser?->can('inventory.locations.manage'))
                    <li class="nav-header">INVENTARIO Y LOTES</li>
                    @can('inventory.view')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.inventory.index') }}"
                                class="nav-link {{ request()->routeIs('admin.inventory.index') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-boxes-stacked"></i>
                                <p>Stock y Lotes FEFO</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.inventory.kardex') }}"
                                class="nav-link {{ request()->routeIs('admin.inventory.kardex') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-clipboard-list"></i>
                                <p>Kardex de Movimientos</p>
                            </a>
                        </li>
                    @endcan
                    @can('inventory.locations.manage')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.inventory.locations.index') }}"
                                class="nav-link {{ request()->routeIs('admin.inventory.locations.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-warehouse"></i>
                                <p>Ubicaciones de Almacén</p>
                            </a>
                        </li>
                    @endcan
                @endif

                {{-- 4. VENTAS Y PEDIDOS --}}
                @if ($adminUser?->can('orders.view') || $adminUser?->can('returns.view') || $adminUser?->can('wholesale.view'))
                    <li class="nav-header">VENTAS Y PEDIDOS</li>
                    @can('orders.view')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.fulfillment.index') }}"
                                class="nav-link {{ request()->routeIs('admin.fulfillment.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-shopping-bag"></i>
                                <p>Pedidos y Pagos</p>
                            </a>
                        </li>
                    @endcan
                    @can('returns.view')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.rma.index') }}"
                                class="nav-link {{ request()->routeIs('admin.rma.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-undo-alt"></i>
                                <p>Devoluciones RMA</p>
                            </a>
                        </li>
                    @endcan
                    @can('wholesale.view')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.wholesale.index') }}"
                                class="nav-link {{ request()->routeIs('admin.wholesale.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-building"></i>
                                <p>Clientes Mayoristas</p>
                            </a>
                        </li>
                    @endcan
                @endif

                {{-- 5. Marketing & Retención (conservar texto exacto para Sprint5Test) --}}
                @if ($adminUser?->can('marketing.coupons') || $adminUser?->can('marketing.abandoned_carts') || $adminUser?->can('marketing.newsletter') || $adminUser?->can('marketing.blog'))
                    <li class="nav-header">Marketing &amp; Retención</li>
                    <li class="nav-item">
                        <a
                            href="{{ route('admin.marketing.index') }}"
                            class="nav-link {{ request()->routeIs('admin.marketing.index') ? 'active' : '' }}"
                        >
                            <i class="nav-icon fas fa-bullhorn"></i>
                            <p>Resumen Marketing</p>
                        </a>
                    </li>
                    @can('marketing.coupons')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.marketing.coupons.index') }}"
                                class="nav-link {{ request()->routeIs('admin.marketing.coupons.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-ticket-alt"></i>
                                <p>Cupones Promocionales</p>
                            </a>
                        </li>
                    @endcan
                    @can('marketing.abandoned_carts')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.marketing.abandoned-carts.index') }}"
                                class="nav-link {{ request()->routeIs('admin.marketing.abandoned-carts.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-cart-arrow-down"></i>
                                <p>Carritos Abandonados</p>
                            </a>
                        </li>
                    @endcan
                    @can('marketing.newsletter')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.marketing.newsletter.index') }}"
                                class="nav-link {{ request()->routeIs('admin.marketing.newsletter.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-envelope-open-text"></i>
                                <p>Newsletter</p>
                            </a>
                        </li>
                    @endcan
                    @can('marketing.blog')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.marketing.blog.index') }}"
                                class="nav-link {{ request()->routeIs('admin.marketing.blog.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-newspaper"></i>
                                <p>Blog Editorial</p>
                            </a>
                        </li>
                    @endcan
                @endif

                {{-- 6. USUARIOS Y ROLES --}}
                @if ($adminUser?->can('users.manage') || $adminUser?->can('security.logs.view'))
                    <li class="nav-header">USUARIOS Y ROLES</li>
                    @can('users.manage')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.users.index') }}"
                                class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-users-cog"></i>
                                <p>Usuarios y Roles</p>
                            </a>
                        </li>
                    @endcan
                    @can('security.logs.view')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.audit.index') }}"
                                class="nav-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-shield-alt"></i>
                                <p>Auditoría de Seguridad</p>
                            </a>
                        </li>
                    @endcan
                @endif

                {{-- 7. CONFIGURACIÓN & PRO --}}
                @if ($adminUser?->can('settings.manage') || $adminUser?->can('announcements.manage') || $adminUser?->can('reviews.moderate'))
                    <li class="nav-header">CONFIGURACIÓN &amp; PRO</li>
                    @can('settings.manage')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.settings.index') }}"
                                class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-sliders-h"></i>
                                <p>White-Label &amp; Temas</p>
                            </a>
                        </li>
                    @endcan
                    @can('announcements.manage')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.announcements.index') }}"
                                class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-bullhorn"></i>
                                <p>Anuncios Topbar</p>
                            </a>
                        </li>
                    @endcan
                    @can('reviews.moderate')
                        <li class="nav-item">
                            <a
                                href="{{ route('admin.reviews.index') }}"
                                class="nav-link {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon fas fa-star-half-stroke"></i>
                                <p>Reseñas Verificadas</p>
                            </a>
                        </li>
                    @endcan
                @endif
            </ul>
        </nav>
    </div>
</aside>
