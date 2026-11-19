@extends('layouts.storefront')

@section('title', 'Mis Direcciones — Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @component('account.partials.shell')
        <div class="grid gap-6 lg:grid-cols-12">
            {{-- Saved Addresses List --}}
            <div class="space-y-4 lg:col-span-7">
                <div class="card-glamora p-6">
                    <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                        Libreta de Direcciones
                    </h2>
                    <p class="mt-1 text-xs text-glamora-muted">
                        Selecciona tu dirección predeterminada para agilizar tus futuros pedidos y despachos B2B.
                    </p>

                    @if ($addresses->isEmpty())
                        <p class="mt-6 rounded-2xl border border-dashed border-glamora-blush/60 bg-glamora-cream/60 p-6 text-center text-sm text-glamora-muted">
                            Aún no has guardado direcciones. Registra tu primera dirección usando el formulario lateral.
                        </p>
                    @else
                        <div class="mt-5 space-y-3.5">
                            @foreach ($addresses as $address)
                                <div @class([
                                    'rounded-2xl border p-4 transition',
                                    'border-glamora-terracotta bg-glamora-cream/80' => $address->is_default,
                                    'border-glamora-blush/35 bg-white' => ! $address->is_default,
                                ])>
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-serif text-lg font-bold text-glamora-charcoal">{{ $address->label }}</span>
                                                @if ($address->is_default)
                                                    <span class="rounded-full bg-glamora-charcoal px-2.5 py-0.5 text-[10px] font-bold uppercase text-glamora-gold">
                                                        Predeterminada
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-sm font-medium text-glamora-charcoal">{{ $address->recipient_name }} · {{ $address->phone }}</p>
                                            <p class="text-xs text-glamora-muted">{{ $address->address_line }}, {{ $address->district }}, {{ $address->city }}</p>
                                            @if ($address->reference)
                                                <p class="mt-1 text-[11px] text-glamora-muted/90">Ref: {{ $address->reference }}</p>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-2">
                                            @if (! $address->is_default)
                                                <form action="{{ route('account.addresses.default', $address) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-full border border-glamora-charcoal/20 bg-white px-3 py-1 text-[11px] font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta hover:text-glamora-terracotta">
                                                        Usar predeterminada
                                                    </button>
                                                </form>
                                            @endif

                                            <form action="{{ route('account.addresses.destroy', $address) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[11px] font-semibold text-rose-700 transition hover:bg-rose-600 hover:text-white">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Add New Address Form --}}
            <div class="lg:col-span-5">
                <div class="card-glamora p-6">
                    <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                        Nueva Dirección
                    </h3>

                    <form action="{{ route('account.addresses.store') }}" method="POST" class="mt-4 space-y-3.5">
                        @csrf

                        <div>
                            <label for="addr_label" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Etiqueta *</label>
                            <input id="addr_label" type="text" name="label" value="{{ old('label') }}" required placeholder="Ej. Casa, Salón Principal" class="input-glamora">
                        </div>

                        <div>
                            <label for="addr_recipient" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Destinatario *</label>
                            <input id="addr_recipient" type="text" name="recipient_name" value="{{ old('recipient_name', $user->name) }}" required class="input-glamora">
                        </div>

                        <div>
                            <label for="addr_phone" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Teléfono *</label>
                            <input id="addr_phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}" required class="input-glamora">
                        </div>

                        <div>
                            <label for="addr_line" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Dirección completa *</label>
                            <input id="addr_line" type="text" name="address_line" value="{{ old('address_line') }}" required placeholder="Av. Benavides 1230, Int. 201" class="input-glamora">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="addr_district" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Distrito *</label>
                                <input id="addr_district" type="text" name="district" value="{{ old('district') }}" required placeholder="Miraflores" class="input-glamora">
                            </div>
                            <div>
                                <label for="addr_city" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Ciudad *</label>
                                <input id="addr_city" type="text" name="city" value="{{ old('city', 'Lima') }}" required class="input-glamora">
                            </div>
                        </div>

                        <div>
                            <label for="addr_ref" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Referencia (opcional)</label>
                            <input id="addr_ref" type="text" name="reference" value="{{ old('reference') }}" placeholder="Puerta gris, frente al parque..." class="input-glamora">
                        </div>

                        <label class="inline-flex cursor-pointer items-center gap-2 pt-1 text-xs text-glamora-charcoal">
                            <input type="checkbox" name="is_default" value="1" @checked(old('is_default')) class="accent-glamora-terracotta">
                            Marcar como dirección predeterminada
                        </label>

                        <button type="submit" class="btn-glamora-primary w-full justify-center">
                            Guardar Dirección
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endcomponent
@endsection
