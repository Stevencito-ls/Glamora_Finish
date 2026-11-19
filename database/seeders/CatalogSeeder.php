<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CustomerTier;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TieredPrice;
use App\Support\CosmeticImageResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $categories = $this->seedCategories();
            $brands = $this->seedBrands();
            $this->seedProductsAndVariants($categories, $brands);
        });
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $parents = [
            'maquillaje' => [
                'name' => 'Maquillaje',
                'description' => 'Fórmulas de alto pigmento, acabados aterciopelados y tonos diseñados para pieles latinas.',
                'image_path' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 1,
                'meta_title' => 'Maquillaje Profesional y Editorial | Glamora Perú',
            ],
            'skincare' => [
                'name' => 'Skincare',
                'description' => 'Activos dermatológicos y extractos botánicos amazónicos con registro sanitario DIGEMID.',
                'image_path' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 2,
                'meta_title' => 'Skincare Dermatológico y Botánico | Glamora Perú',
            ],
            'capilar-y-body-care' => [
                'name' => 'Capilar & Body Care',
                'description' => 'Elixires reparadores con sacha inchi, argán y ceramidas para nutrición profunda.',
                'image_path' => 'https://images.unsplash.com/photo-1526947425960-945c6e72858f?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 3,
                'meta_title' => 'Cuidado Capilar y Corporal | Glamora Perú',
            ],
            'kits-y-profesional-b2b' => [
                'name' => 'Kits & Profesional B2B',
                'description' => 'Exhibidores, packs de cabina y reposición mayorista para salones, spas y boutiques.',
                'image_path' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 4,
                'meta_title' => 'Kits Mayoristas B2B para Salones | Glamora Perú',
            ],
        ];

        $map = [];

        foreach ($parents as $slug => $data) {
            $map[$slug] = Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'parent_id' => null,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'image_path' => $data['image_path'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                    'meta_title' => $data['meta_title'],
                    'meta_description' => $data['description'],
                ]
            );
        }

        $children = [
            'labiales-y-gloss' => [
                'parent' => 'maquillaje',
                'name' => 'Labiales & Gloss',
                'description' => 'Labiales líquidos mate, satinados y gloss con péptidos hidratantes.',
                'image_path' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 1,
            ],
            'rostro-y-bases' => [
                'parent' => 'maquillaje',
                'name' => 'Rostro & Bases',
                'description' => 'Bases sérum, correctores y rubores líquidos de larga duración.',
                'image_path' => 'https://images.unsplash.com/photo-1616683693504-3ea7e9ad6fec?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 2,
            ],
            'ojos-y-cejas' => [
                'parent' => 'maquillaje',
                'name' => 'Ojos & Cejas',
                'description' => 'Delineadores waterproof y máscaras de pestañas con aceite de ricino.',
                'image_path' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 3,
            ],
            'serums-y-activos' => [
                'parent' => 'skincare',
                'name' => 'Sérums & Activos',
                'description' => 'Vitamina C estabilizada, niacinamida y ácido hialurónico multi-molecular.',
                'image_path' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 1,
            ],
            'limpieza-e-hidratacion' => [
                'parent' => 'skincare',
                'name' => 'Limpieza & Hidratación',
                'description' => 'Cremas barrera con ceramidas y limpiadores suaves sin sulfatos.',
                'image_path' => 'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 2,
            ],
            'proteccion-solar' => [
                'parent' => 'skincare',
                'name' => 'Protección Solar',
                'description' => 'Filtros minerales y fluidos toque seco FPS 50+ sin residuo blanco.',
                'image_path' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 3,
            ],
            'tratamientos-capilares' => [
                'parent' => 'capilar-y-body-care',
                'name' => 'Tratamientos Capilares',
                'description' => 'Óleos botánicos termo-protectores y mascarillas reconstructivas.',
                'image_path' => 'https://images.unsplash.com/photo-1608248597359-0e6d526a6828?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 1,
            ],
            'packs-mayoristas-salon' => [
                'parent' => 'kits-y-profesional-b2b',
                'name' => 'Packs Mayoristas Salón',
                'description' => 'Cajas cerradas y exhibidores listos para punto de venta y cabina profesional.',
                'image_path' => 'https://images.unsplash.com/photo-1612817288484-6f916006741a?auto=format&fit=crop&w=1000&q=85',
                'sort_order' => 1,
            ],
        ];

        foreach ($children as $slug => $data) {
            $map[$slug] = Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'parent_id' => $map[$data['parent']]->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'image_path' => $data['image_path'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                    'meta_title' => "{$data['name']} | Glamora Perú",
                    'meta_description' => $data['description'],
                ]
            );
        }

        return $map;
    }

    /**
     * @return array<string, Brand>
     */
    private function seedBrands(): array
    {
        $brandsData = [
            'aruma-atelier' => [
                'name' => 'Aruma Atelier',
                'country_origin' => 'Perú',
                'description' => 'Maquillaje de alta gama formulado para el clima húmedo de la costa y la radiación andina.',
                'logo_path' => 'https://images.unsplash.com/photo-1616683693504-3ea7e9ad6fec?auto=format&fit=crop&w=1000&q=85',
                'banner_path' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1400&q=85',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            'lumiere-botanique' => [
                'name' => 'Lumiére Botanique',
                'country_origin' => 'Perú / Francia',
                'description' => 'Cosmética botánica con camu camu, sacha inchi y activos antioxidantes prensados en frío.',
                'logo_path' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85',
                'banner_path' => 'https://images.unsplash.com/photo-1608248597359-0e6d526a6828?auto=format&fit=crop&w=1400&q=85',
                'is_featured' => true,
                'sort_order' => 2,
            ],
            'velvet-and-rose' => [
                'name' => 'Velvet & Rose',
                'country_origin' => 'Perú',
                'description' => 'Colorimetría editorial en labiales y rubores veganos de textura aterciopelada.',
                'logo_path' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
                'banner_path' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1400&q=85',
                'is_featured' => true,
                'sort_order' => 3,
            ],
            'kora-derma' => [
                'name' => 'Kora Derma',
                'country_origin' => 'Corea del Sur / Perú',
                'description' => 'Dermocosmética minimalista enfocada en reparación de barrera cutánea y control de sebo.',
                'logo_path' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=1000&q=85',
                'banner_path' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1400&q=85',
                'is_featured' => true,
                'sort_order' => 4,
            ],
            'solea-mineral' => [
                'name' => 'Soléa Mineral',
                'country_origin' => 'España / Perú',
                'description' => 'Fotoprotección mineral reef-safe de textura invisible para uso urbano y deportivo.',
                'logo_path' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1000&q=85',
                'banner_path' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=1400&q=85',
                'is_featured' => false,
                'sort_order' => 5,
            ],
        ];

        $map = [];

        foreach ($brandsData as $slug => $data) {
            $map[$slug] = Brand::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'country_origin' => $data['country_origin'],
                    'description' => $data['description'],
                    'logo_path' => $data['logo_path'],
                    'banner_path' => $data['banner_path'],
                    'website_url' => "https://glamora.pe/marcas/{$slug}",
                    'is_cruelty_free' => true,
                    'is_featured' => $data['is_featured'],
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ]
            );
        }

        return $map;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Brand>  $brands
     */
    private function seedProductsAndVariants(array $categories, array $brands): void
    {
        $catalog = [
            [
                'slug' => 'labial-liquido-velvet-matte',
                'name' => 'Labial Líquido Velvet Matte 12H',
                'category' => 'labiales-y-gloss',
                'brand' => 'velvet-and-rose',
                'short_description' => 'Color intenso de acabado mate aterciopelado que no reseca ni transfiere.',
                'description' => 'Fórmula enriquecida con manteca de cupuaçu amazónico y vitamina E que sella el pigmento por hasta 12 horas sin marcar líneas.',
                'sanitary_registration' => 'NSOC54120-24PE',
                'ingredients' => 'Isododecane, Dimethicone, Trimethylsiloxysilicate, Theobroma Grandiflorum Seed Butter, Tocopheryl Acetate, Silica, Iron Oxides (CI 77491, CI 77499).',
                'how_to_use' => 'Aplica desde el arco de cupido hacia las comisuras en una sola pasada uniforme y deja fijar durante 30 segundos.',
                'skin_type' => 'Todo tipo de piel',
                'finish_type' => 'Mate aterciopelado',
                'is_featured' => true,
                'is_new_launch' => false,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-VR-LAB-01', 'shade_name' => 'Terracota Limeña', 'hex_color' => '#A63A46', 'volume_ml' => 5.5, 'base_price' => 49.00, 'wholesale_base_price' => 36.00, 'cost_price' => 19.50, 'barcode' => '7759001001011', 'is_default' => true],
                    ['sku' => 'GLM-VR-LAB-02', 'shade_name' => 'Borgoña Barranco', 'hex_color' => '#7B1E32', 'volume_ml' => 5.5, 'base_price' => 49.00, 'wholesale_base_price' => 36.00, 'cost_price' => 19.50, 'barcode' => '7759001001028', 'is_default' => false],
                    ['sku' => 'GLM-VR-LAB-03', 'shade_name' => 'Rosa Nude Miraflores', 'hex_color' => '#C77A74', 'volume_ml' => 5.5, 'base_price' => 49.00, 'wholesale_base_price' => 36.00, 'cost_price' => 19.50, 'barcode' => '7759001001035', 'is_default' => false],
                    ['sku' => 'GLM-VR-LAB-04', 'shade_name' => 'Coral Paracas', 'hex_color' => '#D8635E', 'volume_ml' => 5.5, 'base_price' => 49.00, 'wholesale_base_price' => 36.00, 'cost_price' => 19.50, 'barcode' => '7759001001042', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'gloss-voluminizador-peptidos',
                'name' => 'Gloss Voluminizador Péptidos & Ácido Hialurónico',
                'category' => 'labiales-y-gloss',
                'brand' => 'aruma-atelier',
                'short_description' => 'Brillo efecto espejo con péptidos biomiméticos y esferas de ácido hialurónico.',
                'description' => 'Tratamiento labial con acabado glossy no pegajoso que hidrata, rellena visiblemente y aporta un velo translúcido luminoso.',
                'sanitary_registration' => 'NSOC55802-24PE',
                'ingredients' => 'Hydrogenated Polyisobutene, Simmondsia Chinensis Seed Oil, Sodium Hyaluronate, Palmitoyl Tripeptide-1, Tocopherol.',
                'how_to_use' => 'Desliza directamente sobre los labios limpios o encima de tu labial favorito para un efecto volumen inmediato.',
                'skin_type' => 'Labios secos o sensibles',
                'finish_type' => 'Gloss espejo',
                'is_featured' => true,
                'is_new_launch' => true,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-AA-GLO-01', 'shade_name' => 'Cristal Rosé', 'hex_color' => '#F2D6D3', 'volume_ml' => 6.0, 'base_price' => 54.00, 'wholesale_base_price' => 39.50, 'cost_price' => 21.00, 'barcode' => '7759001002018', 'is_default' => true],
                    ['sku' => 'GLM-AA-GLO-02', 'shade_name' => 'Malva Glow', 'hex_color' => '#C97A7E', 'volume_ml' => 6.0, 'base_price' => 54.00, 'wholesale_base_price' => 39.50, 'cost_price' => 21.00, 'barcode' => '7759001002025', 'is_default' => false],
                    ['sku' => 'GLM-AA-GLO-03', 'shade_name' => 'Caramelo Dorado', 'hex_color' => '#B86A54', 'volume_ml' => 6.0, 'base_price' => 54.00, 'wholesale_base_price' => 39.50, 'cost_price' => 21.00, 'barcode' => '7759001002032', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'base-serum-luminosa-fps30',
                'name' => 'Base Sérum Luminosa FPS 30',
                'category' => 'rostro-y-bases',
                'brand' => 'aruma-atelier',
                'short_description' => 'Cobertura modulable segunda piel con niacinamida al 2% y protección solar diaria.',
                'description' => 'Base híbrida de textura fluida respirable que unifica el tono, difumina poros y resiste la humedad sin oxidar.',
                'sanitary_registration' => 'NSOC56114-24PE',
                'ingredients' => 'Aqua, Cyclopentasiloxane, Titanium Dioxide, Niacinamide, Glycerin, Squalane, Sodium Hyaluronate, Iron Oxides.',
                'how_to_use' => 'Agita antes de usar. Aplica 2 pulsaciones con brocha kabuki o esponja húmeda desde el centro del rostro hacia afuera.',
                'skin_type' => 'Normal, mixta y seca',
                'finish_type' => 'Luminoso natural',
                'is_featured' => true,
                'is_new_launch' => false,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-AA-BAS-110', 'shade_name' => '110 Marfil Cálido', 'hex_color' => '#F3D3B8', 'volume_ml' => 30.0, 'base_price' => 89.00, 'wholesale_base_price' => 66.00, 'cost_price' => 35.00, 'barcode' => '7759001003015', 'is_default' => true],
                    ['sku' => 'GLM-AA-BAS-210', 'shade_name' => '210 Arena Lima', 'hex_color' => '#DFB38E', 'volume_ml' => 30.0, 'base_price' => 89.00, 'wholesale_base_price' => 66.00, 'cost_price' => 35.00, 'barcode' => '7759001003022', 'is_default' => false],
                    ['sku' => 'GLM-AA-BAS-310', 'shade_name' => '310 Canela Dorada', 'hex_color' => '#C68E64', 'volume_ml' => 30.0, 'base_price' => 89.00, 'wholesale_base_price' => 66.00, 'cost_price' => 35.00, 'barcode' => '7759001003039', 'is_default' => false],
                    ['sku' => 'GLM-AA-BAS-410', 'shade_name' => '410 Cacao Andino', 'hex_color' => '#9D6844', 'volume_ml' => 30.0, 'base_price' => 89.00, 'wholesale_base_price' => 66.00, 'cost_price' => 35.00, 'barcode' => '7759001003046', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'rubor-liquido-sedoso-botanical',
                'name' => 'Rubor Líquido Sedoso Botanical Cheek',
                'category' => 'rostro-y-bases',
                'brand' => 'velvet-and-rose',
                'short_description' => 'Pigmentación ultra difuminable con extracto de rosa mosqueta andina.',
                'description' => 'Una sola gota aporta rubor saludable modulable que se funde con la piel sin levantar la base.',
                'sanitary_registration' => 'NSOC57019-24PE',
                'ingredients' => 'Caprylyl Methicone, Rosa Canina Fruit Oil, Mica, Silica, Tocopheryl Acetate, CI 15850, CI 77891.',
                'how_to_use' => 'Coloca un punto en cada pómulo y difumina con toques suaves usando la yema de los dedos o esponja.',
                'skin_type' => 'Todo tipo de piel',
                'finish_type' => 'Satinado suave',
                'is_featured' => false,
                'is_new_launch' => true,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-VR-RUB-01', 'shade_name' => 'Durazno Sunset', 'hex_color' => '#E07876', 'volume_ml' => 12.0, 'base_price' => 62.00, 'wholesale_base_price' => 46.00, 'cost_price' => 24.00, 'barcode' => '7759001004012', 'is_default' => true],
                    ['sku' => 'GLM-VR-RUB-02', 'shade_name' => 'Frambuesa Chic', 'hex_color' => '#C85A6E', 'volume_ml' => 12.0, 'base_price' => 62.00, 'wholesale_base_price' => 46.00, 'cost_price' => 24.00, 'barcode' => '7759001004029', 'is_default' => false],
                    ['sku' => 'GLM-VR-RUB-03', 'shade_name' => 'Terracota Sol', 'hex_color' => '#B55249', 'volume_ml' => 12.0, 'base_price' => 62.00, 'wholesale_base_price' => 46.00, 'cost_price' => 24.00, 'barcode' => '7759001004036', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'delineador-precision-waterproof',
                'name' => 'Delineador Plumón Precisión 24H Waterproof',
                'category' => 'ojos-y-cejas',
                'brand' => 'aruma-atelier',
                'short_description' => 'Punta micro-fieltro japonesa resistente al agua, sudor y lágrimas.',
                'description' => 'Trazo continuo sin grumos desde líneas finas hasta cat-eye dramático, oftalmológicamente testeado.',
                'sanitary_registration' => 'NSOC58210-25PE',
                'ingredients' => 'Aqua, Styrene/Acrylates Copolymer, Butylene Glycol, Panthenol, Carbon Black (CI 77266).',
                'how_to_use' => 'Traza desde el lagrimal hacia el extremo del ojo apoyando ligeramente la punta inclinada.',
                'skin_type' => 'Ojos sensibles y usuarios de lentes de contacto',
                'finish_type' => 'Mate vinilo',
                'is_featured' => false,
                'is_new_launch' => false,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-AA-DEL-01', 'shade_name' => 'Negro Obsidiana', 'hex_color' => '#171717', 'volume_ml' => 1.2, 'base_price' => 42.00, 'wholesale_base_price' => 31.00, 'cost_price' => 15.00, 'barcode' => '7759001005019', 'is_default' => true],
                    ['sku' => 'GLM-AA-DEL-02', 'shade_name' => 'Espresso Profundo', 'hex_color' => '#4B3328', 'volume_ml' => 1.2, 'base_price' => 42.00, 'wholesale_base_price' => 31.00, 'cost_price' => 15.00, 'barcode' => '7759001005026', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'serum-vitamina-c-camu-camu',
                'name' => 'Sérum Vitamina C 15% + Ácido Ferúlico Amazónico',
                'category' => 'serums-y-activos',
                'brand' => 'lumiere-botanique',
                'short_description' => 'Concentrado antioxidante iluminador con extracto puro de camu camu peruano.',
                'description' => 'Reduce manchas post-inflamatorias, unifica la luminosidad y protege frente al estrés oxidativo urbano en Lima.',
                'sanitary_registration' => 'NSOC59301-25PE',
                'ingredients' => 'Aqua, 3-O-Ethyl Ascorbic Acid (15%), Myrciaria Dubia Fruit Extract, Ferulic Acid, Tocopherol, Propanediol, Sodium Hyaluronate.',
                'how_to_use' => 'Aplica 4 gotas cada mañana sobre rostro limpio y seco antes de tu hidratante y protector solar.',
                'skin_type' => 'Opaca, con manchas o signos de fatiga',
                'finish_type' => 'Sérum ligero de rápida absorción',
                'is_featured' => true,
                'is_new_launch' => true,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-LB-VITC-30', 'shade_name' => '30 ml Gotero Ámbar', 'hex_color' => '#F4B860', 'volume_ml' => 30.0, 'base_price' => 118.00, 'wholesale_base_price' => 86.00, 'cost_price' => 45.00, 'barcode' => '7759001006016', 'is_default' => true],
                    ['sku' => 'GLM-LB-VITC-50', 'shade_name' => '50 ml Formato Profesional', 'hex_color' => '#E99F3D', 'volume_ml' => 50.0, 'base_price' => 168.00, 'wholesale_base_price' => 122.00, 'cost_price' => 64.00, 'barcode' => '7759001006023', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'serum-niacinamida-10-zinc',
                'name' => 'Sérum Niacinamida 10% + Zinc PCA Seborregulador',
                'category' => 'serums-y-activos',
                'brand' => 'kora-derma',
                'short_description' => 'Minimiza poros dilatados, equilibra el brillo y calma rojeces.',
                'description' => 'Fórmula dermatológica libre de fragancias y aceites esenciales con pH balanceado 5.5 para piel mixta o con tendencia acnéica.',
                'sanitary_registration' => 'NSOC60118-25PE',
                'ingredients' => 'Aqua, Niacinamide (10%), Pentylene Glycol, Zinc PCA (1%), Centella Asiatica Extract, Allantoin.',
                'how_to_use' => 'Usa 3 a 4 gotas mañana y noche después del limpiador facial.',
                'skin_type' => 'Mixta, grasa o con imperfecciones',
                'finish_type' => 'Acabado seco sedoso',
                'is_featured' => true,
                'is_new_launch' => false,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-KD-NIA-30', 'shade_name' => '30 ml Uso Diario', 'hex_color' => '#DCE8E6', 'volume_ml' => 30.0, 'base_price' => 79.00, 'wholesale_base_price' => 58.00, 'cost_price' => 29.00, 'barcode' => '7759001007013', 'is_default' => true],
                    ['sku' => 'GLM-KD-NIA-50', 'shade_name' => '50 ml Cabina', 'hex_color' => '#C5DCD8', 'volume_ml' => 50.0, 'base_price' => 115.00, 'wholesale_base_price' => 84.00, 'cost_price' => 42.00, 'barcode' => '7759001007020', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'crema-gel-ceramidas-escualano',
                'name' => 'Crema Gel Reparadora Ceramidas + Escualano',
                'category' => 'limpieza-e-hidratacion',
                'brand' => 'kora-derma',
                'short_description' => 'Hidratación 72 horas que restaura la barrera lipídica sin obstruir poros.',
                'description' => 'Complejo de 5 ceramidas biomiméticas, pantenol y escualano vegetal de olivo en textura gel-crema fresca.',
                'sanitary_registration' => 'NSOC60844-25PE',
                'ingredients' => 'Aqua, Squalane, Glycerin, Ceramide NP, Ceramide AP, Ceramide EOP, Panthenol, Madecassoside.',
                'how_to_use' => 'Aplica una cantidad del tamaño de una almendra como último paso de hidratación día y noche.',
                'skin_type' => 'Sensible, deshidratada o post-procedimiento',
                'finish_type' => 'Hidratado elástico sin grasa',
                'is_featured' => false,
                'is_new_launch' => false,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-KD-CER-50', 'shade_name' => '50 ml Pote Airless', 'hex_color' => '#F8F4EC', 'volume_ml' => 50.0, 'base_price' => 94.00, 'wholesale_base_price' => 69.00, 'cost_price' => 36.00, 'barcode' => '7759001008010', 'is_default' => true],
                    ['sku' => 'GLM-KD-CER-100', 'shade_name' => '100 ml Repuesto Eco', 'hex_color' => '#EFE8DA', 'volume_ml' => 100.0, 'base_price' => 149.00, 'wholesale_base_price' => 109.00, 'cost_price' => 56.00, 'barcode' => '7759001008027', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'fluido-solar-mineral-fps50',
                'name' => 'Fluido Solar Mineral Toque Seco FPS 50+ PA++++',
                'category' => 'proteccion-solar',
                'brand' => 'solea-mineral',
                'short_description' => 'Protección UVA/UVB y luz azul 100% mineral sin efecto mimo.',
                'description' => 'Óxido de zinc micronizado con ectoína y vitamina E resistente al agua durante 80 minutos, ideal para radiación UV extrema en Perú.',
                'sanitary_registration' => 'NSOC61205-25PE',
                'ingredients' => 'Aqua, Zinc Oxide (Non-Nano 18%), Caprylic/Capric Triglyceride, Ectoin, Bisabolol, Tocopherol.',
                'how_to_use' => 'Aplica generosamente 15 minutos antes de la exposición solar y reaplica cada 2 a 3 horas.',
                'skin_type' => 'Todo tipo de piel, incluso rosácea',
                'finish_type' => 'Toque seco invisible',
                'is_featured' => true,
                'is_new_launch' => true,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-SM-SOL-INV', 'shade_name' => 'Invisible Sin Tinte', 'hex_color' => '#FAF7F2', 'volume_ml' => 50.0, 'base_price' => 84.00, 'wholesale_base_price' => 61.00, 'cost_price' => 32.00, 'barcode' => '7759001009017', 'is_default' => true],
                    ['sku' => 'GLM-SM-SOL-TON', 'shade_name' => 'Tono Universal Adaptativo', 'hex_color' => '#D7A984', 'volume_ml' => 50.0, 'base_price' => 88.00, 'wholesale_base_price' => 64.00, 'cost_price' => 33.50, 'barcode' => '7759001009024', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'elixir-capilar-sacha-inchi-argan',
                'name' => 'Elixir Capilar Reparador Sacha Inchi & Argán',
                'category' => 'tratamientos-capilares',
                'brand' => 'lumiere-botanique',
                'short_description' => 'Sella puntas abiertas, controla el frizz en clima húmedo y protege hasta 230°C.',
                'description' => 'Combinación de aceites vírgenes prensados en frío ricos en omega 3, 6 y 9 para brillo espejo sin apelmazar.',
                'sanitary_registration' => 'NSOC61992-25PE',
                'ingredients' => 'Plukenetia Volubilis (Sacha Inchi) Seed Oil, Argania Spinosa Kernel Oil, Coco-Caprylate, Tocopherol.',
                'how_to_use' => 'Distribuye 2 a 3 gotas de medios a puntas sobre el cabello húmedo antes de secar o en seco para finalizar.',
                'skin_type' => 'Cabello teñido, seco o con frizz',
                'finish_type' => 'Brillo sedoso ligero',
                'is_featured' => false,
                'is_new_launch' => false,
                'is_b2b_kit' => false,
                'variants' => [
                    ['sku' => 'GLM-LB-CAP-60', 'shade_name' => '60 ml Travel Size', 'hex_color' => '#D4A145', 'volume_ml' => 60.0, 'base_price' => 69.00, 'wholesale_base_price' => 50.00, 'cost_price' => 25.00, 'barcode' => '7759001010013', 'is_default' => true],
                    ['sku' => 'GLM-LB-CAP-120', 'shade_name' => '120 ml Formato Salón', 'hex_color' => '#B88428', 'volume_ml' => 120.0, 'base_price' => 112.00, 'wholesale_base_price' => 82.00, 'cost_price' => 41.00, 'barcode' => '7759001010020', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'pack-cabina-facial-kora-derma-b2b',
                'name' => 'Pack Cabina Facial Profesional Kora Derma (12 Unidades)',
                'category' => 'packs-mayoristas-salon',
                'brand' => 'kora-derma',
                'short_description' => 'Kit mayorista para centros estéticos y spas con protocolo completo de barrera cutánea.',
                'description' => 'Incluye 12 frascos de tamaño cabina listos para protocolos faciales hidratantes o reventa en recepción de spa.',
                'sanitary_registration' => 'NSOC62410-25PE',
                'ingredients' => 'Niacinamide, Ceramide Complex, Centella Asiatica, Squalane, Hyaluronic Acid.',
                'how_to_use' => 'Uso exclusivo profesional en cabina o exhibición en punto de venta autorizado Glamora B2B.',
                'skin_type' => 'Protocolo multipiel profesional',
                'finish_type' => 'Kit B2B Caja Sellada x12',
                'is_featured' => true,
                'is_new_launch' => false,
                'is_b2b_kit' => true,
                'variants' => [
                    ['sku' => 'GLM-B2B-KD-HID', 'shade_name' => 'Set Limpieza + Hidratación x12', 'hex_color' => '#2E6F6B', 'weight_grams' => 1450.0, 'base_price' => 820.00, 'wholesale_base_price' => 640.00, 'cost_price' => 380.00, 'barcode' => '7759002001010', 'is_default' => true],
                    ['sku' => 'GLM-B2B-KD-ANT', 'shade_name' => 'Set Activos + Vitamina C x12', 'hex_color' => '#C98A3A', 'weight_grams' => 1320.0, 'base_price' => 940.00, 'wholesale_base_price' => 730.00, 'cost_price' => 430.00, 'barcode' => '7759002001027', 'is_default' => false],
                ],
            ],
            [
                'slug' => 'exhibidor-labiales-velvet-matte-b2b',
                'name' => 'Exhibidor Boutique Labiales Velvet Matte (24 Unidades)',
                'category' => 'packs-mayoristas-salon',
                'brand' => 'velvet-and-rose',
                'short_description' => 'Display acrílico con 24 labiales Velvet Matte más 4 probadores de cortesía.',
                'description' => 'Solución llave en mano para peluquerías, boutiques y concept stores con los 4 tonos best-seller de la marca.',
                'sanitary_registration' => 'NSOC54120-24PE',
                'ingredients' => 'Isododecane, Theobroma Grandiflorum Seed Butter, Vitamin E, Mineral Pigments.',
                'how_to_use' => 'Colocar el display acrílico en mostrador principal; incluye probadores etiquetados.',
                'skin_type' => 'Retail boutique / salón',
                'finish_type' => 'Exhibidor B2B x24',
                'is_featured' => true,
                'is_new_launch' => true,
                'is_b2b_kit' => true,
                'variants' => [
                    ['sku' => 'GLM-B2B-VR-NUD', 'shade_name' => 'Colección Nudes & Rosas x24', 'hex_color' => '#C77A74', 'weight_grams' => 980.0, 'base_price' => 960.00, 'wholesale_base_price' => 740.00, 'cost_price' => 410.00, 'barcode' => '7759002002017', 'is_default' => true],
                    ['sku' => 'GLM-B2B-VR-ROJ', 'shade_name' => 'Colección Rojos & Terracotas x24', 'hex_color' => '#A63A46', 'weight_grams' => 980.0, 'base_price' => 960.00, 'wholesale_base_price' => 740.00, 'cost_price' => 410.00, 'barcode' => '7759002002024', 'is_default' => false],
                ],
            ],
        ];

        foreach ($catalog as $item) {
            $slug = $item['slug'];
            $gallery = CosmeticImageResolver::curatedGalleryForProduct($slug);
            $mainImage = $gallery[0];

            $product = Product::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categories[$item['category']]->id,
                    'brand_id' => $brands[$item['brand']]->id,
                    'name' => $item['name'],
                    'short_description' => $item['short_description'],
                    'description' => $item['description'],
                    'sanitary_registration' => $item['sanitary_registration'],
                    'ingredients' => $item['ingredients'],
                    'how_to_use' => $item['how_to_use'],
                    'skin_type' => $item['skin_type'],
                    'finish_type' => $item['finish_type'],
                    'min_stock_alert' => $item['is_b2b_kit'] ? 3 : 12,
                    'reorder_point' => $item['is_b2b_kit'] ? 6 : 25,
                    'is_featured' => $item['is_featured'],
                    'is_new_launch' => $item['is_new_launch'],
                    'is_b2b_kit' => $item['is_b2b_kit'],
                    'is_active' => true,
                    'meta_title' => "{$item['name']} | Glamora",
                    'meta_description' => $item['short_description'],
                    'main_image' => $mainImage,
                    'gallery' => $gallery,
                ]
            );

            foreach ($item['variants'] as $variantData) {
                $variantImage = CosmeticImageResolver::resolve(null, 'variant', $variantData['sku']);

                $variant = ProductVariant::query()->updateOrCreate(
                    ['sku' => $variantData['sku']],
                    [
                        'product_id' => $product->id,
                        'shade_name' => $variantData['shade_name'],
                        'hex_color' => $variantData['hex_color'],
                        'weight_grams' => $variantData['weight_grams'] ?? null,
                        'volume_ml' => $variantData['volume_ml'] ?? null,
                        'base_price' => $variantData['base_price'],
                        'wholesale_base_price' => $variantData['wholesale_base_price'],
                        'cost_price' => $variantData['cost_price'],
                        'barcode' => $variantData['barcode'],
                        'image_path' => $variantImage,
                        'is_default' => $variantData['is_default'],
                        'is_active' => true,
                    ]
                );

                $this->seedTieredPricesForVariant($variant);
            }
        }
    }

    private function seedTieredPricesForVariant(ProductVariant $variant): void
    {
        $base = (float) $variant->base_price;

        $rules = [
            // Minorista por volumen
            ['tier' => CustomerTier::Minorista, 'min' => 1, 'max' => 2, 'discount' => 0.0],
            ['tier' => CustomerTier::Minorista, 'min' => 3, 'max' => null, 'discount' => 5.0],
            // Bronce
            ['tier' => CustomerTier::Bronce, 'min' => 1, 'max' => 5, 'discount' => 10.0],
            ['tier' => CustomerTier::Bronce, 'min' => 6, 'max' => null, 'discount' => 14.0],
            // Plata
            ['tier' => CustomerTier::Plata, 'min' => 1, 'max' => 9, 'discount' => 18.0],
            ['tier' => CustomerTier::Plata, 'min' => 10, 'max' => null, 'discount' => 22.0],
            // Oro
            ['tier' => CustomerTier::Oro, 'min' => 1, 'max' => 11, 'discount' => 25.0],
            ['tier' => CustomerTier::Oro, 'min' => 12, 'max' => null, 'discount' => 30.0],
        ];

        foreach ($rules as $rule) {
            $unitPrice = round($base * (1 - ($rule['discount'] / 100)), 2);

            TieredPrice::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'customer_tier' => $rule['tier']->value,
                    'min_quantity' => $rule['min'],
                ],
                [
                    'max_quantity' => $rule['max'],
                    'unit_price' => $unitPrice,
                    'discount_percent' => $rule['discount'],
                ]
            );
        }
    }
}
