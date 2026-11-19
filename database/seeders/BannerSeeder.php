<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BannerPosition;
use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            // 1. Hero Principal — Slide 1 (Colección Editorial 2026)
            [
                'position' => BannerPosition::HeroPrincipal->value,
                'sort_order' => 1,
                'eyebrow' => 'Colección Editorial 2026',
                'title' => 'Alta cosmética botánica diseñada para el clima y subtono latino.',
                'subtitle' => 'Pigmentos aterciopelados de larga duración y activos amazónicos con registro sanitario DIGEMID y trazabilidad FEFO en cada lote.',
                'badge_text' => 'NSOC Verificado · Cruelty-Free',
                'button_text' => 'Explorar Catálogo',
                'button_url' => '/catalogo',
                'secondary_button_text' => 'Ver Kits B2B',
                'secondary_button_url' => '/catalogo?b2b=1',
                'image_path' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1400&q=85',
                'accent_image_path' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=800&q=85',
                'is_active' => true,
            ],
            // 2. Hero Principal — Slide 2 (Alianzas Comerciales B2B)
            [
                'position' => BannerPosition::HeroPrincipal->value,
                'sort_order' => 2,
                'eyebrow' => 'Alianzas Comerciales B2B',
                'title' => 'Abastece tu salón, spa o boutique con hasta 30% de descuento mayorista.',
                'subtitle' => 'Escalas Bronce, Plata y Oro con exhibidores llave en mano, packs de cabina profesional y línea de crédito aprobada hasta a 30 días.',
                'badge_text' => 'Crédito 30 días · Envíos a todo el Perú',
                'button_text' => 'Programa Mayoristas',
                'button_url' => '/mayoristas',
                'secondary_button_text' => 'Calcular Margen',
                'secondary_button_url' => '/mayoristas#calculadora-b2b',
                'image_path' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1400&q=85',
                'accent_image_path' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=800&q=85',
                'is_active' => true,
            ],
            // 3. Hero Principal — Slide 3 (Journal Dermocosmético)
            [
                'position' => BannerPosition::HeroPrincipal->value,
                'sort_order' => 3,
                'eyebrow' => 'Journal Dermocosmético',
                'title' => 'Ciencia botánica y colorimetría aplicada para tu rutina diaria o cabina.',
                'subtitle' => 'Descubre cómo combinar Vitamina C de camu camu, ceramidas reparadoras y protección mineral FPS 50+ frente a la radiación andina.',
                'badge_text' => 'Protocolos & Shoppable Stories',
                'button_text' => 'Leer Guías del Blog',
                'button_url' => '/blog',
                'secondary_button_text' => 'Rastrear Pedido',
                'secondary_button_url' => '/rastreo',
                'image_path' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1400&q=85',
                'accent_image_path' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=800&q=85',
                'is_active' => true,
            ],
            // 4. Promo Secundario 1 — Colorimetría Velvet Matte
            [
                'position' => BannerPosition::PromoSecundario->value,
                'sort_order' => 1,
                'eyebrow' => 'Edición Limitada Terracota & Nude',
                'title' => 'Labiales Velvet Matte 12H con manteca de cupuaçu amazónico.',
                'subtitle' => 'Pigmento puro de alta fijación que sella la hidratación sin transferir. Ahorra 5% llevando 3 o más tonos combinables.',
                'badge_text' => '3+ uds. con descuento automático',
                'button_text' => 'Descubrir Tonos',
                'button_url' => '/producto/labial-liquido-velvet-matte',
                'secondary_button_text' => 'Ver Labiales & Gloss',
                'secondary_button_url' => '/catalogo?categoria=labiales-y-gloss',
                'image_path' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=1200&q=85',
                'accent_image_path' => 'https://images.unsplash.com/photo-1631214524020-7e18db9a8f92?auto=format&fit=crop&w=800&q=85',
                'is_active' => true,
            ],
            // 5. Promo Secundario 2 — Dermocosmética Barrera & Sol
            [
                'position' => BannerPosition::PromoSecundario->value,
                'sort_order' => 2,
                'eyebrow' => 'Dermo-Barrera & Fotoprotección',
                'title' => 'Sérums antioxidantes de Camu Camu y Ceramidas biomiméticas.',
                'subtitle' => 'Fórmulas clínicas libres de fragancia con registro sanitario DIGEMID y despacho garantizado bajo estándar FEFO.',
                'badge_text' => 'Lotes Frescos · Envío Gratis desde S/ 180',
                'button_text' => 'Explorar Skincare',
                'button_url' => '/catalogo?categoria=skincare',
                'secondary_button_text' => 'Ver Sérums',
                'secondary_button_url' => '/catalogo?categoria=serums-y-activos',
                'image_path' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=1200&q=85',
                'accent_image_path' => 'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=800&q=85',
                'is_active' => true,
            ],
            // 6. Portal Mayorista B2B
            [
                'position' => BannerPosition::PortalMayorista->value,
                'sort_order' => 1,
                'eyebrow' => 'Canal Profesional Glamora B2B',
                'title' => 'Rentabilidad mayorista y reposición trazable FEFO para salones, spas y boutiques.',
                'subtitle' => 'Accede a precios escalonados Bronce (-10%), Plata (-18%) y Oro (-25% a -30%), matriz de pedido rápido por SKU y línea de crédito corporativa.',
                'badge_text' => 'RUC 10 / 20 · Facturación Electrónica',
                'button_text' => 'Postular Cuenta B2B',
                'button_url' => '#formulario-b2b',
                'secondary_button_text' => 'Ver Packs de Cabina',
                'secondary_button_url' => '/catalogo?b2b=1',
                'image_path' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1400&q=85',
                'accent_image_path' => 'https://images.unsplash.com/photo-1612817288484-6f916006741a?auto=format&fit=crop&w=800&q=85',
                'is_active' => true,
            ],
        ];

        foreach ($banners as $bannerData) {
            Banner::query()->updateOrCreate(
                [
                    'position' => $bannerData['position'],
                    'title' => $bannerData['title'],
                ],
                $bannerData
            );
        }
    }
}
