<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class CosmeticImageResolver
{
    /**
     * Fotografías editoriales curadas por producto (imagen principal + galería de 3 fotos).
     *
     * @var array<string, list<string>>
     */
    private const array PRODUCT_GALLERIES = [
        'labial-liquido-velvet-matte' => [
            'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1631214524020-7e18db9a8f92?auto=format&fit=crop&w=1000&q=85',
        ],
        'gloss-voluminizador-peptidos' => [
            'https://images.unsplash.com/photo-1631214540553-ff044a3ff1d4?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=1000&q=85',
        ],
        'base-serum-luminosa-fps30' => [
            'https://images.unsplash.com/photo-1616683693504-3ea7e9ad6fec?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1599305090598-fe179d501227?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1000&q=85',
        ],
        'rubor-liquido-sedoso-botanical' => [
            'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=1000&q=85',
        ],
        'delineador-precision-waterproof' => [
            'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1583241800698-e8ab01830a07?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1000&q=85',
        ],
        'serum-vitamina-c-camu-camu' => [
            'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1608248597359-0e6d526a6828?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1000&q=85',
        ],
        'serum-niacinamida-10-zinc' => [
            'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1617897903246-719242758050?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1000&q=85',
        ],
        'crema-gel-ceramidas-escualano' => [
            'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1000&q=85',
        ],
        'fluido-solar-mineral-fps50' => [
            'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1526947425960-945c6e72858f?auto=format&fit=crop&w=1000&q=85',
        ],
        'elixir-capilar-sacha-inchi-argan' => [
            'https://images.unsplash.com/photo-1608248597359-0e6d526a6828?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1526947425960-945c6e72858f?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1000&q=85',
        ],
        'pack-cabina-facial-kora-derma-b2b' => [
            'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1612817288484-6f916006741a?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?auto=format&fit=crop&w=1000&q=85',
        ],
        'exhibidor-labiales-velvet-matte-b2b' => [
            'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=1000&q=85',
        ],
    ];

    /**
     * Fotografías reales curadas por SKU de variante.
     *
     * @var array<string, string>
     */
    private const array VARIANT_IMAGES = [
        'GLM-VR-LAB-01' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
        'GLM-VR-LAB-02' => 'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=1000&q=85',
        'GLM-VR-LAB-03' => 'https://images.unsplash.com/photo-1631214524020-7e18db9a8f92?auto=format&fit=crop&w=1000&q=85',
        'GLM-VR-LAB-04' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1000&q=85',
        'GLM-AA-GLO-01' => 'https://images.unsplash.com/photo-1631214540553-ff044a3ff1d4?auto=format&fit=crop&w=1000&q=85',
        'GLM-AA-GLO-02' => 'https://images.unsplash.com/photo-1631214524020-7e18db9a8f92?auto=format&fit=crop&w=1000&q=85',
        'GLM-AA-GLO-03' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
        'GLM-AA-BAS-110' => 'https://images.unsplash.com/photo-1616683693504-3ea7e9ad6fec?auto=format&fit=crop&w=1000&q=85',
        'GLM-AA-BAS-210' => 'https://images.unsplash.com/photo-1599305090598-fe179d501227?auto=format&fit=crop&w=1000&q=85',
        'GLM-AA-BAS-310' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85',
        'GLM-AA-BAS-410' => 'https://images.unsplash.com/photo-1608248597359-0e6d526a6828?auto=format&fit=crop&w=1000&q=85',
        'GLM-VR-RUB-01' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=1000&q=85',
        'GLM-VR-RUB-02' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1000&q=85',
        'GLM-VR-RUB-03' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
        'GLM-AA-DEL-01' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=1000&q=85',
        'GLM-AA-DEL-02' => 'https://images.unsplash.com/photo-1583241800698-e8ab01830a07?auto=format&fit=crop&w=1000&q=85',
        'GLM-LB-VITC-30' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85',
        'GLM-LB-VITC-50' => 'https://images.unsplash.com/photo-1608248597359-0e6d526a6828?auto=format&fit=crop&w=1000&q=85',
        'GLM-KD-NIA-30' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=1000&q=85',
        'GLM-KD-NIA-50' => 'https://images.unsplash.com/photo-1617897903246-719242758050?auto=format&fit=crop&w=1000&q=85',
        'GLM-KD-CER-50' => 'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=1000&q=85',
        'GLM-KD-CER-100' => 'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?auto=format&fit=crop&w=1000&q=85',
        'GLM-SM-SOL-INV' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1000&q=85',
        'GLM-SM-SOL-TON' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=1000&q=85',
        'GLM-LB-CAP-60' => 'https://images.unsplash.com/photo-1608248597359-0e6d526a6828?auto=format&fit=crop&w=1000&q=85',
        'GLM-LB-CAP-120' => 'https://images.unsplash.com/photo-1526947425960-945c6e72858f?auto=format&fit=crop&w=1000&q=85',
        'GLM-B2B-KD-HID' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1000&q=85',
        'GLM-B2B-KD-ANT' => 'https://images.unsplash.com/photo-1612817288484-6f916006741a?auto=format&fit=crop&w=1000&q=85',
        'GLM-B2B-VR-NUD' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1000&q=85',
        'GLM-B2B-VR-ROJ' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
    ];

    /**
     * Fotografías editoriales por categoría.
     *
     * @var array<string, string>
     */
    private const array CATEGORY_IMAGES = [
        'maquillaje' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1000&q=85',
        'skincare' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85',
        'capilar-y-body-care' => 'https://images.unsplash.com/photo-1526947425960-945c6e72858f?auto=format&fit=crop&w=1000&q=85',
        'kits-y-profesional-b2b' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1000&q=85',
        'labiales-y-gloss' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
        'rostro-y-bases' => 'https://images.unsplash.com/photo-1616683693504-3ea7e9ad6fec?auto=format&fit=crop&w=1000&q=85',
        'ojos-y-cejas' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=1000&q=85',
        'serums-y-activos' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=1000&q=85',
        'limpieza-e-hidratacion' => 'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=1000&q=85',
        'proteccion-solar' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1000&q=85',
        'tratamientos-capilares' => 'https://images.unsplash.com/photo-1608248597359-0e6d526a6828?auto=format&fit=crop&w=1000&q=85',
        'packs-mayoristas-salon' => 'https://images.unsplash.com/photo-1612817288484-6f916006741a?auto=format&fit=crop&w=1000&q=85',
    ];

    /**
     * Fotografías de identidad de marca (logo/emblema fotográfico y banner editorial).
     *
     * @var array<string, array{logo: string, banner: string}>
     */
    private const array BRAND_IMAGES = [
        'aruma-atelier' => [
            'logo' => 'https://images.unsplash.com/photo-1616683693504-3ea7e9ad6fec?auto=format&fit=crop&w=1000&q=85',
            'banner' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1400&q=85',
        ],
        'lumiere-botanique' => [
            'logo' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85',
            'banner' => 'https://images.unsplash.com/photo-1608248597359-0e6d526a6828?auto=format&fit=crop&w=1400&q=85',
        ],
        'velvet-and-rose' => [
            'logo' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
            'banner' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1400&q=85',
        ],
        'kora-derma' => [
            'logo' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=1000&q=85',
            'banner' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1400&q=85',
        ],
        'solea-mineral' => [
            'logo' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1000&q=85',
            'banner' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=1400&q=85',
        ],
    ];

    /**
     * Portadas fotográficas editoriales para el blog (Beauty Journal).
     *
     * @var array<string, string>
     */
    private const array POST_IMAGES = [
        'rutina-vitamina-c-humedad-lima' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1200&q=85',
        'guia-tonos-labiales-subtono-latino' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1200&q=85',
        'gestion-cabina-b2b-rentabilidad-salon' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1200&q=85',
    ];

    /**
     * Pool general de respaldo de alta cosmética.
     *
     * @var list<string>
     */
    private const array DEFAULT_POOL = [
        'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
        'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85',
        'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1000&q=85',
        'https://images.unsplash.com/photo-1616683693504-3ea7e9ad6fec?auto=format&fit=crop&w=1000&q=85',
        'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=1000&q=85',
        'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=1000&q=85',
    ];

    public static function resolve(?string $path, string $type = 'product', ?string $key = null): string
    {
        $trimmed = trim((string) $path);

        if ($trimmed !== '' && ! self::isSvgPath($trimmed)) {
            if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
                return $trimmed;
            }

            if (preg_match('#^/?storage/#i', $trimmed) === 1) {
                $cleanStoragePath = ltrim((string) preg_replace('#^/?storage/#i', '', $trimmed), '/');

                return asset('storage/' . $cleanStoragePath);
            }

            $cleanPath = ltrim($trimmed, '/');

            if ($cleanPath !== '' && Storage::disk('public')->exists($cleanPath)) {
                return asset('storage/' . $cleanPath);
            }

            if ($cleanPath !== '' && is_file(public_path($cleanPath))) {
                return asset($cleanPath);
            }
        }

        return self::fallbackFor($type, $key, $trimmed);
    }

    /**
     * @return list<string>
     */
    public static function resolveGallery(mixed $gallery, ?string $mainImage = null, ?string $key = null): array
    {
        $items = [];

        if (is_string($gallery) && trim($gallery) !== '') {
            $decoded = json_decode($gallery, true);
            if (is_array($decoded)) {
                $gallery = $decoded;
            }
        }

        if (is_string($mainImage) && trim($mainImage) !== '' && ! self::isSvgPath($mainImage)) {
            $items[] = self::resolve($mainImage, 'product', $key);
        }

        if (is_array($gallery)) {
            foreach ($gallery as $entry) {
                if (! is_string($entry)) {
                    continue;
                }

                $candidate = trim($entry);
                if ($candidate === '' || self::isSvgPath($candidate)) {
                    continue;
                }

                $resolved = self::resolve($candidate, 'product', $key);
                if (! in_array($resolved, $items, true)) {
                    $items[] = $resolved;
                }
            }
        }

        $curated = self::curatedGalleryForProduct($key);

        foreach ($curated as $fallbackUrl) {
            if (count($items) >= 3) {
                break;
            }

            if (! in_array($fallbackUrl, $items, true)) {
                $items[] = $fallbackUrl;
            }
        }

        return array_values($items);
    }

    /**
     * @return list<string>
     */
    public static function curatedGalleryForProduct(?string $slug): array
    {
        $cleanKey = strtolower(trim((string) $slug));

        if ($cleanKey !== '' && isset(self::PRODUCT_GALLERIES[$cleanKey])) {
            return self::PRODUCT_GALLERIES[$cleanKey];
        }

        return [
            self::DEFAULT_POOL[0],
            self::DEFAULT_POOL[1],
            self::DEFAULT_POOL[2],
        ];
    }

    private static function isSvgPath(string $path): bool
    {
        $withoutQuery = (string) strtok($path, '?#');

        return str_ends_with(strtolower(trim($withoutQuery)), '.svg');
    }

    private static function fallbackFor(string $type, ?string $key = null, string $originalPath = ''): string
    {
        $lookupKey = trim((string) $key);

        if ($lookupKey === '' && $originalPath !== '') {
            $lookupKey = pathinfo((string) strtok($originalPath, '?#'), PATHINFO_FILENAME);
        }

        $normalizedSlug = strtolower($lookupKey);
        $normalizedSku = strtoupper($lookupKey);

        return match ($type) {
            'variant' => self::VARIANT_IMAGES[$normalizedSku]
                ?? self::PRODUCT_GALLERIES[$normalizedSlug][0]
                ?? self::deterministicFromPool($lookupKey),
            'category' => self::CATEGORY_IMAGES[$normalizedSlug]
                ?? self::deterministicFromPool($lookupKey),
            'brand' => self::BRAND_IMAGES[$normalizedSlug]['logo']
                ?? self::deterministicFromPool($lookupKey),
            'brand_banner' => self::BRAND_IMAGES[$normalizedSlug]['banner']
                ?? 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1400&q=85',
            'banner' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1400&q=85',
            'post' => self::POST_IMAGES[$normalizedSlug]
                ?? 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1200&q=85',
            default => self::PRODUCT_GALLERIES[$normalizedSlug][0]
                ?? self::VARIANT_IMAGES[$normalizedSku]
                ?? self::deterministicFromPool($lookupKey),
        };
    }

    private static function deterministicFromPool(string $seed): string
    {
        if ($seed === '') {
            return self::DEFAULT_POOL[0];
        }

        $index = abs(crc32($seed)) % count(self::DEFAULT_POOL);

        return self::DEFAULT_POOL[$index];
    }
}
