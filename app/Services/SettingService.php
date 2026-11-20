<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SettingService
{
    public const string CACHE_KEY = 'glamora_store_settings_all';

    public const string ANNOUNCEMENTS_CACHE_KEY = 'glamora_active_announcements';

    /**
     * @var array<string, array{
     *     value: mixed,
     *     type: string,
     *     group: string,
     *     label: string,
     *     description: string,
     *     is_public: bool
     * }>
     */
    private const array DEFINITIONS = [
        'store_name' => [
            'value' => 'Glamora Perú',
            'type' => 'string',
            'group' => 'identity',
            'label' => 'Nombre comercial de la tienda',
            'description' => 'Se muestra en cabecera, títulos SEO, PDFs y mensajes de WhatsApp.',
            'is_public' => true,
        ],
        'store_tagline' => [
            'value' => 'Alta Cosmética Botánica & Distribución B2B',
            'type' => 'string',
            'group' => 'identity',
            'label' => 'Subtítulo / Eslogan',
            'description' => 'Frase breve que acompaña a la marca en storefront y catálogo PDF.',
            'is_public' => true,
        ],
        'store_ruc' => [
            'value' => '20608945123',
            'type' => 'string',
            'group' => 'identity',
            'label' => 'RUC / Identificador fiscal',
            'description' => 'RUC de la empresa mostrado en cotizaciones, notas de pedido y catálogo PDF.',
            'is_public' => true,
        ],
        'store_email' => [
            'value' => 'info.glamora@gmail.com',
            'type' => 'string',
            'group' => 'identity',
            'label' => 'Correo de contacto comercial',
            'description' => 'Email visible para clientes minoristas y mayoristas.',
            'is_public' => true,
        ],
        'store_phone' => [
            'value' => '+51 917 328 085',
            'type' => 'string',
            'group' => 'identity',
            'label' => 'Teléfono de atención',
            'description' => 'Número telefónico de showroom o central comercial.',
            'is_public' => true,
        ],
        'store_address' => [
            'value' => 'Almacén Central: Lima Metropolitana, Perú (Envíos a todo el país vía Olva y Shalom)',
            'type' => 'string',
            'group' => 'identity',
            'label' => 'Dirección fiscal / Showroom',
            'description' => 'Dirección mostrada en pie de página y documentos PDF.',
            'is_public' => true,
        ],
        'store_logo_path' => [
            'value' => null,
            'type' => 'path',
            'group' => 'identity',
            'label' => 'Logotipo personalizado',
            'description' => 'Ruta pública del logotipo raster cargado (JPG/PNG/WEBP).',
            'is_public' => true,
        ],
        'store_favicon_path' => [
            'value' => null,
            'type' => 'path',
            'group' => 'identity',
            'label' => 'Favicon personalizado',
            'description' => 'Ruta pública del favicon (.ico o .png).',
            'is_public' => true,
        ],
        'social_instagram' => [
            'value' => 'https://instagram.com/glamora.pe',
            'type' => 'string',
            'group' => 'social',
            'label' => 'Instagram URL',
            'description' => 'Enlace al perfil oficial de Instagram.',
            'is_public' => true,
        ],
        'social_facebook' => [
            'value' => 'https://facebook.com/glamora.pe',
            'type' => 'string',
            'group' => 'social',
            'label' => 'Facebook URL',
            'description' => 'Enlace a la página oficial de Facebook.',
            'is_public' => true,
        ],
        'social_tiktok' => [
            'value' => 'https://tiktok.com/@glamoracosmeticos',
            'type' => 'string',
            'group' => 'social',
            'label' => 'TikTok URL',
            'description' => 'Enlace al perfil oficial de TikTok.',
            'is_public' => true,
        ],
        'whatsapp_number' => [
            'value' => '51917328085',
            'type' => 'string',
            'group' => 'whatsapp',
            'label' => 'Número WhatsApp Business',
            'description' => 'Número internacional con código de país (ej. 51917328085) para pedidos y botón flotante.',
            'is_public' => true,
        ],
        'whatsapp_checkout_enabled' => [
            'value' => true,
            'type' => 'boolean',
            'group' => 'whatsapp',
            'label' => 'Checkout por WhatsApp habilitado',
            'description' => 'Permite registrar órdenes y continuar coordinación vía WhatsApp desde bolsa y checkout.',
            'is_public' => true,
        ],
        'whatsapp_floating_enabled' => [
            'value' => true,
            'type' => 'boolean',
            'group' => 'whatsapp',
            'label' => 'Botón flotante de WhatsApp',
            'description' => 'Muestra el acceso rápido flotante de atención en el storefront.',
            'is_public' => true,
        ],
        'free_shipping_threshold' => [
            'value' => 199.00,
            'type' => 'float',
            'group' => 'commerce',
            'label' => 'Umbral de envío gratis (S/)',
            'description' => 'Monto mínimo de compra neta para habilitar envío estándar gratuito.',
            'is_public' => true,
        ],
        'tax_rate' => [
            'value' => 0.18,
            'type' => 'float',
            'group' => 'commerce',
            'label' => 'Tasa IGV decimal (ej. 0.18)',
            'description' => 'Tasa de impuesto incluida en precios de catálogo y checkout.',
            'is_public' => true,
        ],
        'active_theme' => [
            'value' => 'glamora_nude',
            'type' => 'string',
            'group' => 'theme',
            'label' => 'Tema visual activo',
            'description' => 'Paleta cromática aplicada mediante CSS Variables en toda la tienda.',
            'is_public' => true,
        ],
        'catalog_pdf_enabled' => [
            'value' => true,
            'type' => 'boolean',
            'group' => 'features',
            'label' => 'Catálogo PDF descargable',
            'description' => 'Habilita la descarga pública del catálogo A4 White-Label en /catalogo/pdf.',
            'is_public' => true,
        ],
        'reviews_auto_approve' => [
            'value' => false,
            'type' => 'boolean',
            'group' => 'features',
            'label' => 'Auto-aprobar reseñas de clientes',
            'description' => 'Si está inactivo, las reseñas quedan pendientes hasta ser aprobadas por un administrador.',
            'is_public' => false,
        ],
        'onboarding_dismissed' => [
            'value' => false,
            'type' => 'boolean',
            'group' => 'system',
            'label' => 'Asistente de configuración oculto',
            'description' => 'Controla la visibilidad de la guía inicial de 4 pasos en el dashboard administrativo.',
            'is_public' => false,
        ],
    ];

    /**
     * @var array<string, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     cream: string,
     *     card: string,
     *     terracotta: string,
     *     blush: string,
     *     gold: string,
     *     charcoal: string,
     *     muted: string
     * }>
     */
    private const array THEMES = [
        'glamora_nude' => [
            'key' => 'glamora_nude',
            'label' => 'Glamora Nude Editorial',
            'description' => 'Crema cálido, terracota rosado, blush #E8B4A2 y acentos dorados.',
            'cream' => '#FAF6F2',
            'card' => '#FFFFFF',
            'terracotta' => '#C87D72',
            'blush' => '#E8B4A2',
            'gold' => '#D4AF37',
            'charcoal' => '#2B2523',
            'muted' => '#6E635F',
        ],
        'noir_gold' => [
            'key' => 'noir_gold',
            'label' => 'Noir & Oro Boutique',
            'description' => 'Contraste editorial nocturno en alabastro, carbón profundo y dorado champagne.',
            'cream' => '#F5F3EE',
            'card' => '#FFFFFF',
            'terracotta' => '#A67C52',
            'blush' => '#D8C8B8',
            'gold' => '#C9A227',
            'charcoal' => '#171513',
            'muted' => '#5B554E',
        ],
        'botanical_skincare' => [
            'key' => 'botanical_skincare',
            'label' => 'Botanical Skincare',
            'description' => 'Estética dermocosmética en verde salvia, lino orgánico y minerales.',
            'cream' => '#F4F7F3',
            'card' => '#FFFFFF',
            'terracotta' => '#4F7A65',
            'blush' => '#B8D2C2',
            'gold' => '#B08D57',
            'charcoal' => '#1F2E27',
            'muted' => '#5B6D63',
        ],
        'corporate_pyme' => [
            'key' => 'corporate_pyme',
            'label' => 'Corporativo PYME & B2B',
            'description' => 'Paleta ejecutiva azul acero y gris perla pensada para distribuidoras B2B.',
            'cream' => '#F4F6F9',
            'card' => '#FFFFFF',
            'terracotta' => '#2E5A88',
            'blush' => '#BFD0E0',
            'gold' => '#C59B27',
            'charcoal' => '#1E293B',
            'muted' => '#64748B',
        ],
    ];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $memoized = null;

    /**
     * @var EloquentCollection<int, Announcement>|null
     */
    private ?EloquentCollection $memoizedAnnouncements = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $memoizedSharedViewData = null;

    private ?bool $hasSettingsTable = null;

    private ?bool $hasAnnouncementsTable = null;

    /**
     * @return array<string, array{
     *     value: mixed,
     *     type: string,
     *     group: string,
     *     label: string,
     *     description: string,
     *     is_public: bool
     * }>
     */
    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->memoized !== null) {
            return $this->memoized;
        }

        $defaults = $this->defaultValuesMap();

        try {
            $stored = Cache::remember(self::CACHE_KEY, 3600, function (): array {
                if (! $this->settingsTableExists()) {
                    return [];
                }

                $rows = StoreSetting::query()->get(['key', 'value', 'type']);
                $result = [];

                foreach ($rows as $row) {
                    $type = self::DEFINITIONS[$row->key]['type'] ?? $row->type ?? 'string';
                    $result[$row->key] = $this->castValue($row->value, (string) $type);
                }

                return $result;
            });
        } catch (Throwable) {
            $stored = [];
        }

        $this->memoized = array_merge($defaults, is_array($stored) ? $stored : []);

        return $this->memoized;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? (self::DEFINITIONS[$key]['value'] ?? null);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array{type?: string, group?: string, label?: string, description?: string, is_public?: bool}  $meta
     */
    public function set(string $key, mixed $value, array $meta = []): void
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        $type = $meta['type'] ?? $definition['type'] ?? 'string';

        StoreSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->serializeValue($value, $type),
                'type' => $type,
                'group' => $meta['group'] ?? $definition['group'] ?? 'general',
                'label' => $meta['label'] ?? $definition['label'] ?? $key,
                'description' => $meta['description'] ?? $definition['description'] ?? null,
                'is_public' => $meta['is_public'] ?? $definition['is_public'] ?? true,
            ]
        );

        $this->clearCache();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $definition = self::DEFINITIONS[$key] ?? null;
            $type = $definition['type'] ?? 'string';

            StoreSetting::query()->updateOrCreate(
                ['key' => (string) $key],
                [
                    'value' => $this->serializeValue($value, $type),
                    'type' => $type,
                    'group' => $definition['group'] ?? 'general',
                    'label' => $definition['label'] ?? (string) $key,
                    'description' => $definition['description'] ?? null,
                    'is_public' => $definition['is_public'] ?? true,
                ]
            );
        }

        $this->clearCache();
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     cream: string,
     *     card: string,
     *     terracotta: string,
     *     blush: string,
     *     gold: string,
     *     charcoal: string,
     *     muted: string
     * }>
     */
    public function themes(): array
    {
        return self::THEMES;
    }

    public function activeThemeKey(): string
    {
        $key = $this->string('active_theme', 'glamora_nude');

        return array_key_exists($key, self::THEMES) ? $key : 'glamora_nude';
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     cream: string,
     *     card: string,
     *     terracotta: string,
     *     blush: string,
     *     gold: string,
     *     charcoal: string,
     *     muted: string
     * }
     */
    public function activeTheme(): array
    {
        return self::THEMES[$this->activeThemeKey()];
    }

    public function logoUrl(): ?string
    {
        return $this->resolvePublicAssetPath($this->get('store_logo_path'), allowIcons: false);
    }

    public function faviconUrl(): ?string
    {
        return $this->resolvePublicAssetPath($this->get('store_favicon_path'), allowIcons: true);
    }

    public function whatsappNumber(): string
    {
        $raw = $this->string('whatsapp_number', '51917328085');
        $digits = (string) preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            return '51917328085';
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51' . $digits;
        }

        return $digits;
    }

    public function buildWhatsappUrl(string $message = '', ?string $phoneOverride = null): string
    {
        $digits = $phoneOverride !== null
            ? (string) preg_replace('/\D+/', '', $phoneOverride)
            : $this->whatsappNumber();

        if ($digits === '') {
            $digits = $this->whatsappNumber();
        }

        $base = "https://wa.me/{$digits}";
        $trimmed = trim($message);

        return $trimmed !== ''
            ? $base . '?text=' . rawurlencode($trimmed)
            : $base;
    }

    public function buildOrderWhatsappUrl(Order $order): string
    {
        $order->loadMissing(['items', 'payments']);

        $storeName = $this->string('store_name', 'Glamora Perú');
        $paymentMethod = $order->payments->first()?->payment_method?->label() ?? 'Por coordinar';

        $lines = [
            "Hola *{$storeName}*, acabo de registrar mi pedido *{$order->order_number}*:",
            "• Cliente: {$order->customer_name}",
            "• Código de seguimiento: {$order->tracking_code}",
            "• Método de envío: {$order->shipping_method->label()}",
            "• Método de pago: {$paymentMethod}",
            '',
            '*Detalle de productos:*',
        ];

        foreach ($order->items as $item) {
            $lines[] = sprintf(
                '- %dx %s (%s) — S/ %s',
                (int) $item->quantity,
                $item->product_name,
                $item->variant_name,
                number_format((float) $item->subtotal, 2)
            );
        }

        if ((float) $order->discount_amount > 0) {
            $lines[] = sprintf('• Descuento aplicado: -S/ %s', number_format((float) $order->discount_amount, 2));
        }

        $lines[] = sprintf('• Envío: %s', (float) $order->shipping_cost > 0 ? 'S/ ' . number_format((float) $order->shipping_cost, 2) : 'GRATIS');
        $lines[] = sprintf('*TOTAL: S/ %s*', number_format((float) $order->total, 2));
        $lines[] = 'Seguimiento: ' . route('tracking.index', ['codigo' => $order->order_number]);

        return $this->buildWhatsappUrl(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function buildCartWhatsappUrl(array $summary, ?string $customerName = null): string
    {
        $storeName = $this->string('store_name', 'Glamora Perú');
        $items = is_array($summary['items'] ?? null) ? $summary['items'] : [];

        if ($items === []) {
            return $this->buildWhatsappUrl("Hola {$storeName}, quisiera recibir asesoría sobre sus productos y precios.");
        }

        $tierLabel = isset($summary['tier']) && is_object($summary['tier']) && method_exists($summary['tier'], 'label')
            ? $summary['tier']->label()
            : 'Minorista';

        $lines = [
            "Hola *{$storeName}*, quiero confirmar mi bolsa de compra ({$tierLabel}):",
        ];

        if ($customerName !== null && trim($customerName) !== '') {
            $lines[] = '• Cliente: ' . trim($customerName);
        }

        $lines[] = '';
        foreach ($items as $item) {
            $lines[] = sprintf(
                '- %dx %s (%s) — S/ %s',
                (int) ($item['quantity'] ?? 1),
                (string) ($item['product_name'] ?? 'Producto'),
                (string) ($item['shade_name'] ?? 'Estándar'),
                number_format((float) ($item['subtotal'] ?? 0.0), 2)
            );
        }

        $lines[] = '';
        if (! empty($summary['coupon_code'])) {
            $lines[] = sprintf(
                '• Cupón (%s): -S/ %s',
                (string) $summary['coupon_code'],
                number_format((float) ($summary['coupon_discount'] ?? 0.0), 2)
            );
        }

        $shippingCost = (float) ($summary['shipping_cost'] ?? 0.0);
        $lines[] = '• Envío estimado: ' . ($shippingCost > 0 ? 'S/ ' . number_format($shippingCost, 2) : 'GRATIS');
        $lines[] = sprintf('*Total estimado: S/ %s*', number_format((float) ($summary['total'] ?? 0.0), 2));

        return $this->buildWhatsappUrl(implode("\n", $lines));
    }

    /**
     * @return EloquentCollection<int, Announcement>
     */
    public function activeAnnouncements(): EloquentCollection
    {
        if ($this->memoizedAnnouncements !== null) {
            return $this->memoizedAnnouncements;
        }

        try {
            $this->memoizedAnnouncements = Cache::remember(self::ANNOUNCEMENTS_CACHE_KEY, 600, function (): EloquentCollection {
                if (! $this->announcementsTableExists()) {
                    return new EloquentCollection();
                }

                return Announcement::query()
                    ->activeNow()
                    ->ordered()
                    ->get();
            });

            return $this->memoizedAnnouncements;
        } catch (Throwable) {
            return new EloquentCollection();
        }
    }

    public function freeShippingThreshold(): float
    {
        return max(0.0, round($this->float('free_shipping_threshold', ShippingService::FREE_SHIPPING_THRESHOLD), 2));
    }

    public function taxRate(): float
    {
        $rate = $this->float('tax_rate', PricingService::TAX_RATE_IGV);

        return min(1.0, max(0.0, round($rate, 4)));
    }

    public function clearCache(): void
    {
        $this->memoized = null;
        $this->memoizedAnnouncements = null;
        $this->memoizedSharedViewData = null;
        $this->hasSettingsTable = null;
        $this->hasAnnouncementsTable = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::ANNOUNCEMENTS_CACHE_KEY);
    }

    public function clearAnnouncementsCache(): void
    {
        $this->memoizedAnnouncements = null;
        $this->memoizedSharedViewData = null;
        $this->hasAnnouncementsTable = null;
        Cache::forget(self::ANNOUNCEMENTS_CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function sharedViewData(): array
    {
        if ($this->memoizedSharedViewData !== null) {
            return $this->memoizedSharedViewData;
        }

        $theme = $this->activeTheme();

        $this->memoizedSharedViewData = [
            'storeSettings' => $this->all(),
            'activeTheme' => $theme,
            'activeThemeKey' => $theme['key'],
            'storeLogoUrl' => $this->logoUrl(),
            'storeFaviconUrl' => $this->faviconUrl(),
            'activeAnnouncements' => $this->activeAnnouncements(),
            'whatsappNumber' => $this->whatsappNumber(),
            'whatsappUrl' => $this->buildWhatsappUrl('Hola, necesito asesoría en ' . $this->string('store_name', 'Glamora Perú') . '.'),
            'whatsappFloatingEnabled' => $this->bool('whatsapp_floating_enabled', true),
            'whatsappCheckoutEnabled' => $this->bool('whatsapp_checkout_enabled', true),
            'catalogPdfEnabled' => $this->bool('catalog_pdf_enabled', true),
            'freeShippingThreshold' => $this->freeShippingThreshold(),
            'taxRate' => $this->taxRate(),
            'taxPercent' => round($this->taxRate() * 100, 2),
        ];

        return $this->memoizedSharedViewData;
    }

    public function settingsTableExists(): bool
    {
        if ($this->hasSettingsTable !== null) {
            return $this->hasSettingsTable;
        }

        $exists = Schema::hasTable('store_settings');
        if ($exists) {
            $this->hasSettingsTable = true;
        }

        return $exists;
    }

    private function announcementsTableExists(): bool
    {
        if ($this->hasAnnouncementsTable !== null) {
            return $this->hasAnnouncementsTable;
        }

        $exists = Schema::hasTable('announcements');
        if ($exists) {
            $this->hasAnnouncementsTable = true;
        }

        return $exists;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultValuesMap(): array
    {
        $defaults = [];

        foreach (self::DEFINITIONS as $key => $meta) {
            $defaults[$key] = $meta['value'];
        }

        return $defaults;
    }

    private function castValue(?string $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'boolean' => in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true),
            'integer' => (int) $raw,
            'float' => (float) $raw,
            'json' => json_decode($raw, true),
            default => $raw,
        };
    }

    private function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'integer' => (string) ((int) $value),
            'float' => (string) ((float) $value),
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
            default => trim((string) $value) !== '' ? trim((string) $value) : null,
        };
    }

    private function resolvePublicAssetPath(mixed $rawPath, bool $allowIcons): ?string
    {
        if (! is_string($rawPath)) {
            return null;
        }

        $clean = trim($rawPath);
        if (
            $clean === ''
            || preg_match('/[\x00-\x1F\x7F]/', $clean) === 1
            || str_contains($clean, '..')
            || str_contains($clean, '\\')
            || str_starts_with($clean, '//')
        ) {
            return null;
        }

        $withoutQuery = strtolower((string) strtok($clean, '?#'));
        if (str_ends_with($withoutQuery, '.svg') || str_ends_with($withoutQuery, '.svgz')) {
            return null;
        }

        if (! $allowIcons && str_ends_with($withoutQuery, '.ico')) {
            return null;
        }

        if (preg_match('/^https?:\/\/\S+$/i', $clean) === 1) {
            return $clean;
        }

        if (preg_match('#^/?storage/#i', $clean) === 1) {
            $relative = ltrim((string) preg_replace('#^/?storage/#i', '', $clean), '/');

            return $relative !== '' ? asset('storage/' . $relative) : null;
        }

        $normalized = ltrim($clean, '/');
        if ($normalized !== '' && Storage::disk('public')->exists($normalized)) {
            return asset('storage/' . $normalized);
        }

        if ($normalized !== '' && is_file(public_path($normalized))) {
            return asset($normalized);
        }

        return null;
    }
}
