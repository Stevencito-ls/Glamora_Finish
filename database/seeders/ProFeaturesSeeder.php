<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $settingService = app(SettingService::class);

        DB::transaction(function () use ($settingService): void {
            $this->seedSettings($settingService);
            $this->seedAnnouncements();
            $this->seedProductReviews();
        });

        $settingService->clearCache();
    }

    private function seedSettings(SettingService $settingService): void
    {
        $legacyPlaceholders = [
            'store_ruc' => ['20608841920'],
            'store_email' => ['ventas@glamora.pe'],
            'store_phone' => ['+51 987 654 321'],
            'store_address' => ['Av. Los Frutales 412, Ate · Showroom Miraflores, Lima'],
            'social_instagram' => ['https://www.instagram.com/glam.orabelleza?knvt=bHd6YTJ3dXVnYjZz'],
            'social_facebook' => ['https://facebook.com/glamoraperu'],
            'social_tiktok' => ['https://tiktok.com/@glamoraperu'],
            'whatsapp_number' => ['51917328085'],
        ];

        foreach ($settingService->definitions() as $key => $meta) {
            $rawValue = match ($meta['type']) {
                'boolean' => filter_var($meta['value'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
                'integer' => (string) ((int) $meta['value']),
                'float' => number_format((float) $meta['value'], 2, '.', ''),
                default => $meta['value'] !== null ? (string) $meta['value'] : null,
            };

            $existing = StoreSetting::query()->where('key', $key)->first();

            if ($existing === null) {
                StoreSetting::query()->create([
                    'key' => $key,
                    'value' => $rawValue,
                    'type' => $meta['type'],
                    'group' => $meta['group'],
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                    'is_public' => $meta['is_public'],
                ]);
            } elseif (
                isset($legacyPlaceholders[$key])
                && in_array((string) $existing->value, $legacyPlaceholders[$key], true)
            ) {
                $existing->update([
                    'value' => $rawValue,
                    'description' => $meta['description'],
                ]);
            }
        }
    }

    private function seedAnnouncements(): void
    {
        $announcements = [
            [
                'title' => 'Envío Estándar GRATIS en compras desde S/ 199.00 a todo el Perú',
                'badge_text' => 'Envío Gratis',
                'link_label' => 'Explorar catálogo',
                'link_url' => '/catalogo',
                'icon' => 'truck',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Usa el cupón GLAMORA10 y obtén 10% de descuento en pedidos desde S/ 120',
                'badge_text' => 'Cupón -10%',
                'link_label' => 'Ver colección',
                'link_url' => '/catalogo',
                'icon' => 'sparkles',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Programa Mayorista B2B: hasta 30% de descuento y línea de crédito a 30 días',
                'badge_text' => 'B2B Oro',
                'link_label' => 'Solicitar cuenta',
                'link_url' => '/mayoristas',
                'icon' => 'building',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Descarga nuestro Catálogo PDF actualizado con precios, tonos y lotes FEFO vigentes',
                'badge_text' => 'Catálogo PDF',
                'link_label' => 'Descargar PDF',
                'link_url' => '/catalogo/pdf',
                'icon' => 'document',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($announcements as $item) {
            Announcement::query()->updateOrCreate(
                ['title' => $item['title']],
                [
                    'badge_text' => $item['badge_text'],
                    'link_label' => $item['link_label'],
                    'link_url' => $item['link_url'],
                    'icon' => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => $item['is_active'],
                    'starts_at' => null,
                    'ends_at' => null,
                ]
            );
        }
    }

    private function seedProductReviews(): void
    {
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->first();
        if (! $cliente instanceof User) {
            return;
        }

        $admin = User::query()->where('email', 'admin@glamora.pe')->first();
        $order1 = Order::query()->where('order_number', 'GLM-2026-0001')->first();
        $order2 = Order::query()->where('order_number', 'GLM-2026-0002')->first();

        $reviewsSpec = [
            [
                'product_slug' => 'serum-vitamina-c-camu-camu',
                'order_id' => $order1?->id,
                'rating' => 5,
                'title' => 'Luminosidad visible desde la primera semana',
                'comment' => 'Se absorbe al instante sin dejar sensación pegajosa en el clima húmedo de Lima. El gotero ámbar protege muy bien la fórmula.',
                'shade_purchased' => '30 ml Gotero Ámbar',
                'is_verified_purchase' => true,
                'status' => 'approved',
                'moderated_by' => $admin?->id,
                'moderated_at' => now()->subDays(4),
                'moderation_notes' => 'Reseña verificada de pedido GLM-2026-0001.',
            ],
            [
                'product_slug' => 'gloss-voluminizador-peptidos',
                'order_id' => $order2?->id,
                'rating' => 5,
                'title' => 'Brillo espejo real sin sensación pegajosa',
                'comment' => 'Lo compré en el tono Cristal Rosé y deja los labios súper hidratados. Llegó con lote vigente y empaque sellado.',
                'shade_purchased' => 'Cristal Rosé',
                'is_verified_purchase' => true,
                'status' => 'approved',
                'moderated_by' => $admin?->id,
                'moderated_at' => now()->subDays(2),
                'moderation_notes' => 'Reseña verificada de pedido GLM-2026-0002.',
            ],
            [
                'product_slug' => 'crema-gel-ceramidas-escualano',
                'order_id' => $order1?->id,
                'rating' => 4,
                'title' => 'Textura fresca ideal para piel mixta',
                'comment' => 'Calma la piel después de usar activos exfoliantes y no saca brillos durante el día. El envase airless es muy higiénico.',
                'shade_purchased' => '50 ml Pote Airless',
                'is_verified_purchase' => true,
                'status' => 'pending',
                'moderated_by' => null,
                'moderated_at' => null,
                'moderation_notes' => null,
            ],
        ];

        $products = Product::query()
            ->whereIn('slug', array_column($reviewsSpec, 'product_slug'))
            ->get()
            ->keyBy('slug');

        foreach ($reviewsSpec as $spec) {
            $product = $products->get($spec['product_slug']);
            if (! $product instanceof Product) {
                continue;
            }

            ProductReview::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'user_id' => $cliente->id,
                ],
                [
                    'order_id' => $spec['order_id'],
                    'rating' => $spec['rating'],
                    'title' => $spec['title'],
                    'comment' => $spec['comment'],
                    'shade_purchased' => $spec['shade_purchased'],
                    'is_verified_purchase' => $spec['is_verified_purchase'],
                    'status' => $spec['status'],
                    'moderated_by' => $spec['moderated_by'],
                    'moderated_at' => $spec['moderated_at'],
                    'moderation_notes' => $spec['moderation_notes'],
                ]
            );
        }
    }
}
