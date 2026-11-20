<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $resolveAdminSection = static function (string $section, string $default = '') use ($__env): string {
            $raw = trim((string) $__env->yieldContent($section, $default));
            if ($raw === '') {
                return $default;
            }

            return html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };
        $adminDocTitle = $resolveAdminSection('title', 'Panel Administrativo — Glamora Perú');
        $adminPageTitle = $resolveAdminSection('page_title', 'Panel de Administración Glamora');
        $adminPageSubtitle = $resolveAdminSection('page_subtitle', '');
        $currentAdminUser = auth()->user();
        $currentRoleName = $currentAdminUser?->getRoleNames()->first() ?? 'Staff';
        $friendlyRoleLabel = $currentRoleName === 'Vendedor B2B' ? 'Vendedor / B2B' : $currentRoleName;
    @endphp
    <title>{{ $adminDocTitle }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Bootstrap 5 + FontAwesome 6 + AdminLTE 3.2 + DataTables Bootstrap 5 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        :root {
            --glamora-charcoal: #2B2523;
            --glamora-terracotta: #C87D72;
            --glamora-gold: #D4AF37;
            --glamora-cream: #FAF6F2;
            --glamora-blush: #E8C5B9;
        }
        body.adminlte-glamora {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f4f6f9;
            color: #2B2523;
        }
        .main-sidebar.sidebar-dark-primary {
            background-color: #211C1A !important;
        }
        .main-sidebar .brand-link {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
            background-color: #1A1614;
        }
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
            background-color: #C87D72 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(200, 125, 114, 0.35);
        }
        .nav-sidebar .nav-header {
            font-size: 0.68rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.12em;
            color: rgba(250, 246, 242, 0.5) !important;
            padding: 0.85rem 1rem 0.35rem !important;
        }
        .content-wrapper {
            background-color: #FAF6F2 !important;
        }
        .small-box {
            border-radius: 0.85rem;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(43, 37, 35, 0.07);
        }
        .small-box .icon > i {
            font-size: 54px;
            top: 15px;
        }
        .card {
            border-radius: 0.85rem;
            box-shadow: 0 2px 10px rgba(43, 37, 35, 0.05);
        }
        .card-header.d-flex::after,
        .card-header .d-flex::after {
            display: none !important;
            content: none !important;
        }
        a {
            text-decoration: none;
        }
    </style>
    @stack('styles')
</head>
<body
    x-data="{ sidebarOpen: false }"
    class="hold-transition sidebar-mini layout-fixed adminlte-glamora antialiased"
>
    <div class="wrapper min-h-screen">
        {{-- Mobile Sidebar Overlay --}}
        <div
            x-show="sidebarOpen"
            x-cloak
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-glamora-charcoal/50 backdrop-blur-xs lg:hidden"
        ></div>

        {{-- Top Navbar AdminLTE --}}
        <nav class="main-header navbar navbar-expand navbar-white navbar-light border-b border-glamora-blush/35 bg-white px-3 py-2.5 shadow-2xs">
            <div class="container-fluid flex flex-wrap items-center justify-between gap-3">
                <ul class="navbar-nav flex items-center gap-2">
                    <li class="nav-item">
                        <a
                            class="nav-link inline-flex h-9 w-9 items-center justify-center rounded-lg border border-glamora-charcoal/15 bg-glamora-cream text-glamora-charcoal"
                            data-widget="pushmenu"
                            href="#"
                            role="button"
                            aria-label="Alternar barra lateral"
                            @click.prevent="sidebarOpen = !sidebarOpen"
                        >
                            <i class="fas fa-bars"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('admin.dashboard') }}" class="nav-link text-xs font-semibold text-glamora-charcoal">
                            <i class="fas fa-chart-pie mr-1 text-glamora-terracotta"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-inline-block">
                        <a href="{{ route('home') }}" class="nav-link text-xs font-semibold text-glamora-charcoal" target="_blank">
                            <i class="fas fa-store mr-1 text-glamora-terracotta"></i> Ver Tienda Glamora
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ml-auto flex items-center gap-2.5">
                    @yield('header_actions')

                    <li class="nav-item d-none d-sm-inline-flex items-center">
                        <span class="badge rounded-pill bg-dark px-3 py-1.5 text-[11px] font-semibold text-white">
                            <i class="fas fa-user-shield mr-1 text-warning"></i>
                            {{ $currentAdminUser?->name }} ({{ $friendlyRoleLabel }})
                        </span>
                    </li>

                    <li class="nav-item">
                        <a
                            href="{{ route('home') }}"
                            class="btn btn-sm btn-outline-secondary rounded-pill px-3 text-xs font-semibold"
                        >
                            <i class="fas fa-external-link-alt mr-1"></i> Tienda
                        </a>
                    </li>

                    <li class="nav-item">
                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-sm btn-outline-danger rounded-pill px-3 text-xs font-semibold"
                            >
                                <i class="fas fa-sign-out-alt mr-1"></i> Salir
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>

        {{-- Main Sidebar Container AdminLTE --}}
        @include('admin.partials.sidebar')

        {{-- Content Wrapper. Contains page content --}}
        <div class="content-wrapper">
            {{-- Content Header (Page header) --}}
            <section class="content-header border-b border-glamora-blush/30 bg-white/80 px-4 py-4 sm:px-6">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-sm-8">
                            <h1 class="m-0 font-serif text-2xl font-bold text-glamora-charcoal sm:text-3xl">
                                {{ $adminPageTitle }}
                            </h1>
                            @if ($adminPageSubtitle !== '')
                                <p class="mt-1 mb-0 text-xs text-glamora-muted">
                                    {{ $adminPageSubtitle }}
                                </p>
                            @endif
                        </div>
                        <div class="col-sm-4 text-sm-end mt-2 mt-sm-0">
                            <ol class="breadcrumb float-sm-right mb-0 bg-transparent p-0 text-xs">
                                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-glamora-terracotta">Admin</a></li>
                                <li class="breadcrumb-item active text-glamora-muted">{{ $adminPageTitle }}</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Main content --}}
            <section class="content px-4 py-5 sm:px-6">
                <div class="container-fluid">
                    @include('admin.partials.alerts')

                    @yield('content')
                </div>
            </section>
        </div>

        <footer class="main-footer border-t border-glamora-blush/30 bg-white px-4 py-3 text-xs text-glamora-muted">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <strong>Glamora Perú &copy; {{ date('Y') }}</strong> · Panel Administrativo B2C &amp; B2B.
                </div>
                <div>
                    <span class="badge bg-light text-dark border">AdminLTE 3.2 · Bootstrap 5 · Spatie RBAC</span>
                </div>
            </div>
        </footer>
    </div>

    {{-- jQuery 3.7 + Bootstrap 5 + AdminLTE 3.2 + DataTables --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
                window.jQuery('table.datatable').each(function () {
                    const $table = window.jQuery(this);
                    if (window.jQuery.fn.DataTable.isDataTable(this)) {
                        return;
                    }
                    const hasColspanRow = $table.find('tbody tr td[colspan]').length > 0;
                    if (hasColspanRow) {
                        return;
                    }
                    $table.DataTable({
                        pageLength: 10,
                        order: [],
                        language: {
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_ registros',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                            infoEmpty: 'Sin registros disponibles',
                            infoFiltered: '(filtrado de _MAX_ totales)',
                            zeroRecords: 'No se encontraron coincidencias',
                            paginate: {
                                first: 'Primero',
                                last: 'Último',
                                next: 'Siguiente',
                                previous: 'Anterior'
                            }
                        }
                    });
                });
            }
        });
    </script>

    @livewireScripts(['url' => asset(config('app.debug') ? 'livewire/livewire.js' : 'livewire/livewire.min.js')])
    @stack('scripts')
</body>
</html>
