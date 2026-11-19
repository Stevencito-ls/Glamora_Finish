<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InventoryMovement;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WarehouseLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedInventory();
        });
    }

    private function seedInventory(): void
    {
        $locations = $this->seedWarehouseLocations();
        $almacenero = User::query()->where('email', 'almacen@glamora.pe')->first()
            ?? User::query()->first();

        $variants = ProductVariant::query()
            ->with(['product.category', 'product.brand'])
            ->orderBy('id')
            ->get();

        // Lotes con vencimiento próximo (25-55 días) para activar alertas FEFO reales en el dashboard.
        $nearExpirySpecs = [
            'GLM-LB-VITC-30' => ['days' => 27, 'qty' => 18],
            'GLM-LB-CAP-60' => ['days' => 29, 'qty' => 12],
            'GLM-AA-GLO-01' => ['days' => 31, 'qty' => 15],
            'GLM-KD-NIA-30' => ['days' => 33, 'qty' => 24],
            'GLM-VR-LAB-01' => ['days' => 38, 'qty' => 22],
            'GLM-VR-LAB-03' => ['days' => 42, 'qty' => 16],
            'GLM-AA-BAS-210' => ['days' => 46, 'qty' => 14],
            'GLM-SM-SOL-INV' => ['days' => 50, 'qty' => 20],
            'GLM-KD-CER-50' => ['days' => 54, 'qty' => 19],
        ];

        $supplierByBrand = [
            'aruma-atelier' => 'Laboratorio Aruma Cosmética S.A.C.',
            'lumiere-botanique' => 'Lumiére Botanique Lab Perú S.A.C.',
            'velvet-and-rose' => 'Velvet & Rose Atelier E.I.R.L.',
            'kora-derma' => 'Kora Derma Importaciones Perú S.A.C.',
            'solea-mineral' => 'Soléa Mineral Dermocosmética S.A.C.',
        ];

        foreach ($variants as $index => $variant) {
            $location = $this->resolveLocationForVariant($variant, $locations);
            $brandSlug = $variant->product?->brand?->slug ?? '';
            $supplier = $supplierByBrand[$brandSlug] ?? 'Distribuidora Cosmética Andina S.A.C.';
            $runningStock = (int) $variant->batches()->sum('current_quantity');

            if (isset($nearExpirySpecs[$variant->sku])) {
                $spec = $nearExpirySpecs[$variant->sku];
                $runningStock = $this->createBatchWithEntryMovement(
                    variant: $variant,
                    location: $location,
                    user: $almacenero,
                    batchNumber: "LOT-2025-{$variant->sku}-F1",
                    manufacturingDate: now()->subMonths(11)->toDateString(),
                    expirationDate: now()->addDays($spec['days'])->toDateString(),
                    quantity: $spec['qty'],
                    supplierName: $supplier,
                    notes: 'Lote prioritario FEFO próximo a vencer; despachar primero.',
                    runningStock: $runningStock,
                );
            }

            $isB2bKit = (bool) ($variant->product?->is_b2b_kit ?? false);
            $freshQty = $isB2bKit
                ? 16 + (($index % 4) * 4)
                : 55 + (($index % 5) * 15);
            $monthsAhead = 14 + ($index % 9);

            $this->createBatchWithEntryMovement(
                variant: $variant,
                location: $location,
                user: $almacenero,
                batchNumber: "LOT-2026-{$variant->sku}-A1",
                manufacturingDate: now()->subMonths(2)->toDateString(),
                expirationDate: now()->addMonths($monthsAhead)->toDateString(),
                quantity: $freshQty,
                supplierName: $supplier,
                notes: 'Lote de reposición estándar con vida útil extendida.',
                runningStock: $runningStock,
            );
        }
    }

    /**
     * @return array<string, WarehouseLocation>
     */
    private function seedWarehouseLocations(): array
    {
        $definitions = [
            [
                'code' => 'ALM-SKN-A01',
                'zone' => 'Skincare Activos (Climatizada)',
                'aisle' => 'A1',
                'rack' => 'R01',
                'shelf' => 'N1',
                'description' => 'Cámara climatizada (15°C - 20°C) para vitamina C, ácido ferúlico y sérums antioxidantes.',
                'temperature_controlled' => true,
            ],
            [
                'code' => 'ALM-SKN-A02',
                'zone' => 'Skincare Barrera & Solares (Climatizada)',
                'aisle' => 'A1',
                'rack' => 'R02',
                'shelf' => 'N2',
                'description' => 'Zona climatizada (16°C - 21°C) para niacinamida, ceramidas y fotoprotectores minerales.',
                'temperature_controlled' => true,
            ],
            [
                'code' => 'ALM-MAQ-B01',
                'zone' => 'Maquillaje Labiales & Gloss',
                'aisle' => 'B1',
                'rack' => 'R01',
                'shelf' => 'N1',
                'description' => 'Estantería seca para labiales líquidos mate, satinados y gloss con péptidos.',
                'temperature_controlled' => false,
            ],
            [
                'code' => 'ALM-MAQ-B02',
                'zone' => 'Maquillaje Rostro & Ojos',
                'aisle' => 'B2',
                'rack' => 'R01',
                'shelf' => 'N2',
                'description' => 'Góndola general para bases sérum, rubores líquidos y delineadores waterproof.',
                'temperature_controlled' => false,
            ],
            [
                'code' => 'ALM-CAP-C01',
                'zone' => 'Capilar & Botánicos',
                'aisle' => 'C1',
                'rack' => 'R01',
                'shelf' => 'N1',
                'description' => 'Zona protegida de luz UV para elixires de sacha inchi, argán y aceites capilares.',
                'temperature_controlled' => false,
            ],
            [
                'code' => 'ALM-B2B-D01',
                'zone' => 'Kits & Mayorista B2B',
                'aisle' => 'D1',
                'rack' => 'R01',
                'shelf' => 'N1',
                'description' => 'Área de alistamiento para exhibidores boutique y cajas cerradas mayoristas.',
                'temperature_controlled' => false,
            ],
            [
                'code' => 'ALM-B2B-D02',
                'zone' => 'Kits Activos B2B (Climatizada)',
                'aisle' => 'D1',
                'rack' => 'R02',
                'shelf' => 'N1',
                'description' => 'Zona climatizada exclusiva para packs faciales de cabina profesional Kora Derma.',
                'temperature_controlled' => true,
            ],
            [
                'code' => 'ALM-CUAR-E01',
                'zone' => 'Cuarentena & Inspección RMA',
                'aisle' => 'E1',
                'rack' => 'R01',
                'shelf' => 'N1',
                'description' => 'Área segregada y climatizada para revisión sanitaria DIGEMID de devoluciones RMA.',
                'temperature_controlled' => true,
            ],
        ];

        $map = [];

        foreach ($definitions as $data) {
            $map[$data['code']] = WarehouseLocation::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'zone' => $data['zone'],
                    'aisle' => $data['aisle'],
                    'rack' => $data['rack'],
                    'shelf' => $data['shelf'],
                    'description' => $data['description'],
                    'temperature_controlled' => $data['temperature_controlled'],
                    'is_active' => true,
                ]
            );
        }

        return $map;
    }

    /**
     * @param  array<string, WarehouseLocation>  $locations
     */
    private function resolveLocationForVariant(ProductVariant $variant, array $locations): WarehouseLocation
    {
        $categorySlug = $variant->product?->category?->slug ?? '';

        return match ($categorySlug) {
            'serums-y-activos' => $locations['ALM-SKN-A01'],
            'limpieza-e-hidratacion', 'proteccion-solar' => $locations['ALM-SKN-A02'],
            'labiales-y-gloss' => $locations['ALM-MAQ-B01'],
            'rostro-y-bases', 'ojos-y-cejas' => $locations['ALM-MAQ-B02'],
            'tratamientos-capilares' => $locations['ALM-CAP-C01'],
            'packs-mayoristas-salon' => str_contains($variant->sku, 'KD')
                ? $locations['ALM-B2B-D02']
                : $locations['ALM-B2B-D01'],
            default => $locations['ALM-MAQ-B01'],
        };
    }

    private function createBatchWithEntryMovement(
        ProductVariant $variant,
        WarehouseLocation $location,
        ?User $user,
        string $batchNumber,
        string $manufacturingDate,
        string $expirationDate,
        int $quantity,
        string $supplierName,
        string $notes,
        int $runningStock,
    ): int {
        $existingBatch = ProductBatch::query()->where('batch_number', $batchNumber)->first();
        if ($existingBatch !== null) {
            return $runningStock;
        }

        $batch = ProductBatch::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_location_id' => $location->id,
            'batch_number' => $batchNumber,
            'manufacturing_date' => $manufacturingDate,
            'expiration_date' => $expirationDate,
            'initial_quantity' => $quantity,
            'current_quantity' => $quantity,
            'reserved_quantity' => 0,
            'unit_cost' => $variant->cost_price,
            'supplier_name' => $supplierName,
            'status' => 'active',
            'notes' => $notes,
        ]);

        $newBalance = $runningStock + $quantity;

        InventoryMovement::query()->create([
            'product_variant_id' => $variant->id,
            'product_batch_id' => $batch->id,
            'warehouse_location_id' => $location->id,
            'user_id' => $user?->id,
            'movement_type' => 'entrada',
            'quantity' => $quantity,
            'balance_after' => $newBalance,
            'unit_cost' => $variant->cost_price,
            'reference_type' => ProductBatch::class,
            'reference_id' => $batch->id,
            'notes' => "Ingreso inicial de lote {$batchNumber} en {$location->code}.",
        ]);

        return $newBalance;
    }
}
