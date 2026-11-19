@if (session('admin_success'))
    <div
        x-data="{ show: true }"
        x-show="show"
        class="mb-6 flex items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-900"
    >
        <div class="flex items-center gap-2.5">
            <svg class="h-5 w-5 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            <span>{{ session('admin_success') }}</span>
        </div>
        <button type="button" @click="show = false" aria-label="Cerrar alerta" class="text-emerald-700 hover:text-emerald-950">
            &times;
        </button>
    </div>
@endif

@if (session('admin_error'))
    <div
        x-data="{ show: true }"
        x-show="show"
        class="mb-6 flex items-center justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-900"
    >
        <span>{{ session('admin_error') }}</span>
        <button type="button" @click="show = false" aria-label="Cerrar error" class="text-rose-700 hover:text-rose-950">
            &times;
        </button>
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900">
        <p class="font-semibold">Revisa los datos ingresados:</p>
        <ul class="mt-1.5 list-disc pl-5 text-xs space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
