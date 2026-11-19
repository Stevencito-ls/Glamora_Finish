@php
    $authUser = auth()->user();
    $tier = $authUser?->effectiveTier() ?? \App\Enums\CustomerTier::Minorista;

    $menu = [
        ['label' => 'Resumen de Cuenta', 'route' => 'account.index', 'pattern' => 'account.index'],
        ['label' => 'Mis Pedidos', 'route' => 'account.orders', 'pattern' => 'account.orders*'],
        ['label' => 'Mis Direcciones', 'route' => 'account.addresses', 'pattern' => 'account.addresses*'],
        ['label' => 'Devoluciones RMA', 'route' => 'account.returns', 'pattern' => 'account.returns*'],
        ['label' => 'Seguridad & 2FA', 'route' => 'account.security', 'pattern' => 'account.security*'],
    ];
@endphp

<section class="border-b border-glamora-blush/35 bg-gradient-to-r from-[#FAF6F2] via-white to-[#F7ECE4]">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-8 sm:px-6 lg:px-8">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="inline-flex rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wider {{ $tier->badgeClasses() }}">
                    {{ $tier->label() }}
                </span>
                @if ($authUser?->two_factor_enabled)
                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-700">
                        2FA Activo
                    </span>
                @endif
            </div>
            <h1 class="mt-2 font-serif text-3xl font-semibold text-glamora-charcoal sm:text-4xl">
                Hola, {{ $authUser?->name }}
            </h1>
            <p class="mt-1 text-xs text-glamora-muted">
                {{ $authUser?->email }} · {{ $authUser?->document_type ?? 'DOC' }} {{ $authUser?->document_number ?? '—' }}
            </p>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn-glamora-outline px-5 py-2 text-xs uppercase tracking-wider">
                Cerrar Sesión
            </button>
        </form>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    @if (session('account_success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-900">
            {{ session('account_success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-900">
            <ul class="list-disc pl-5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-8 lg:grid-cols-12">
        <aside class="lg:col-span-3">
            <nav aria-label="Menú Mi Cuenta" class="card-glamora space-y-1 p-3">
                @foreach ($menu as $item)
                    @php
                        $active = request()->routeIs($item['pattern']);
                    @endphp
                    <a
                        href="{{ route($item['route']) }}"
                        @class([
                            'block rounded-xl px-4 py-2.5 text-sm font-semibold transition',
                            'bg-glamora-charcoal text-glamora-cream' => $active,
                            'text-glamora-charcoal hover:bg-glamora-cream hover:text-glamora-terracotta' => ! $active,
                        ])
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach

                <div class="mt-2 border-t border-glamora-blush/30 pt-2">
                    <a
                        href="{{ route('cart.index') }}"
                        class="block rounded-xl px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-glamora-terracotta hover:bg-glamora-cream"
                    >
                        Ir a mi Carrito &rarr;
                    </a>
                </div>
            </nav>
        </aside>

        <div class="space-y-6 lg:col-span-9">
            {{ $slot }}
        </div>
    </div>
</div>
