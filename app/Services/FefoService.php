<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReturnCondition;
use App\Exceptions\InsufficientFefoStockException;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FefoService
{
    /**
     * Asigna lotes por orden FEFO (expiration_date ASC, id ASC) para un pedido.
     *
     * @return list<array{batch_id: int, batch_number: string, quantity: int, expiration_date: string}>
     */
    public function allocateForOrder(
        ProductVariant $variant,
        int $quantityNeeded,
        Order $order,
        string $mode = 'reserve',
        ?User $actor = null,
    ): array {
        if ($quantityNeeded < 1) {
            throw new InvalidArgumentException('La cantidad a asignar por FEFO debe ser mayor o igual a 1.');
        }

        if (! in_array($mode, ['reserve', 'dispatch'], true)) {
            throw new InvalidArgumentException("Modo FEFO inválido: {$mode}.");
        }

        return DB::transaction(function () use ($variant, $quantityNeeded, $order, $mode, $actor): array {
            $remaining = $quantityNeeded;
            $allocations = [];
            $today = now()->toDateString();

            $batches = ProductBatch::query()
                ->where('product_variant_id', $variant->id)
                ->where('status', 'active')
                ->whereDate('expiration_date', '>', $today)
                ->whereColumn('current_quantity', '>', 'reserved_quantity')
                ->orderBy('expiration_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $available = $batch->availableQuantity();
                if ($available <= 0) {
                    continue;
                }

                $take = min($remaining, $available);

                if ($mode === 'dispatch') {
                    $batch->current_quantity = max(0, (int) $batch->current_quantity - $take);
                    if ($batch->current_quantity <= 0) {
                        $batch->reserved_quantity = 0;
                        $batch->status = 'depleted';
                    }
                    $batch->save();

                    $movementType = 'salida';
                    $movementNote = "Salida FEFO por despacho de pedido {$order->order_number} (lote {$batch->batch_number}).";
                } else {
                    $batch->reserved_quantity = (int) $batch->reserved_quantity + $take;
                    $batch->save();

                    $movementType = 'reserva';
                    $movementNote = "Reserva FEFO asignada a pedido {$order->order_number} (lote {$batch->batch_number}).";
                }

                $variant->unsetRelation('batches');
                $variantBalanceAfter = $variant->availableStock();

                InventoryMovement::query()->create([
                    'product_variant_id' => $variant->id,
                    'product_batch_id' => $batch->id,
                    'warehouse_location_id' => $batch->warehouse_location_id,
                    'user_id' => $actor?->id,
                    'movement_type' => $movementType,
                    'quantity' => -$take,
                    'balance_after' => $variantBalanceAfter,
                    'unit_cost' => $batch->unit_cost,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'notes' => $movementNote,
                ]);

                $allocations[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $take,
                    'expiration_date' => $batch->expiration_date?->toDateString() ?? $today,
                ];

                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InsufficientFefoStockException(
                    "Stock FEFO insuficiente para {$variant->sku} ({$variant->shade_name}). Faltante: {$remaining} unidad(es)."
                );
            }

            return $allocations;
        });
    }

    /**
     * Convierte reservas FEFO del pedido en salidas físicas definitivas al despachar.
     */
    public function dispatchReservedOrder(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $alreadyDispatched = $lockedOrder->fefo_dispatched_at !== null
                || InventoryMovement::query()
                    ->where('reference_type', Order::class)
                    ->where('reference_id', $lockedOrder->id)
                    ->where('movement_type', 'salida')
                    ->exists();

            if ($alreadyDispatched) {
                return;
            }

            $lockedOrder->loadMissing('items.variant');

            foreach ($lockedOrder->items as $item) {
                if (! $item->variant instanceof ProductVariant) {
                    continue;
                }

                $allocations = $this->extractItemAllocations($item);
                if ($allocations === []) {
                    $newAllocations = $this->allocateForOrder(
                        variant: $item->variant,
                        quantityNeeded: $item->quantity,
                        order: $lockedOrder,
                        mode: 'dispatch',
                        actor: $actor,
                    );
                    $item->update([
                        'product_batch_id' => $newAllocations[0]['batch_id'] ?? null,
                        'batch_allocations' => $newAllocations,
                    ]);

                    continue;
                }

                foreach ($allocations as $alloc) {
                    $batch = ProductBatch::query()->lockForUpdate()->find($alloc['batch_id']);
                    if (! $batch instanceof ProductBatch) {
                        continue;
                    }

                    $take = min((int) $alloc['quantity'], (int) $batch->current_quantity);
                    if ($take <= 0) {
                        continue;
                    }

                    $batch->reserved_quantity = max(0, (int) $batch->reserved_quantity - $take);
                    $batch->current_quantity = max(0, (int) $batch->current_quantity - $take);
                    if ($batch->current_quantity <= 0) {
                        $batch->reserved_quantity = 0;
                        $batch->status = 'depleted';
                    }
                    $batch->save();

                    $item->variant->unsetRelation('batches');
                    $balanceAfter = $item->variant->availableStock();

                    InventoryMovement::query()->create([
                        'product_variant_id' => $item->variant->id,
                        'product_batch_id' => $batch->id,
                        'warehouse_location_id' => $batch->warehouse_location_id,
                        'user_id' => $actor?->id,
                        'movement_type' => 'salida',
                        'quantity' => -$take,
                        'balance_after' => $balanceAfter,
                        'unit_cost' => $batch->unit_cost,
                        'reference_type' => Order::class,
                        'reference_id' => $lockedOrder->id,
                        'notes' => "Despacho físico FEFO de reserva para pedido {$lockedOrder->order_number} (lote {$batch->batch_number}).",
                    ]);
                }
            }
        });
    }

    /**
     * Libera stock reservado o despachado al cancelar un pedido.
     */
    public function releaseForCancellation(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $alreadyReleased = InventoryMovement::query()
                ->where('reference_type', Order::class)
                ->where('reference_id', $lockedOrder->id)
                ->where('movement_type', 'liberacion')
                ->exists();

            if ($alreadyReleased) {
                return;
            }

            $lockedOrder->loadMissing('items.variant');
            $wasDispatched = $lockedOrder->fefo_dispatched_at !== null;
            $today = now()->toDateString();

            foreach ($lockedOrder->items as $item) {
                if (! $item->variant instanceof ProductVariant) {
                    continue;
                }

                $allocations = $this->extractItemAllocations($item);

                foreach ($allocations as $alloc) {
                    $batch = ProductBatch::query()->lockForUpdate()->find($alloc['batch_id']);
                    if (! $batch instanceof ProductBatch) {
                        continue;
                    }

                    $qty = (int) $alloc['quantity'];
                    if ($qty <= 0) {
                        continue;
                    }

                    if ($wasDispatched) {
                        $batch->current_quantity = (int) $batch->current_quantity + $qty;
                        if (
                            $batch->status === 'depleted'
                            && $batch->expiration_date !== null
                            && $batch->expiration_date->toDateString() > $today
                        ) {
                            $batch->status = 'active';
                        }
                        $restoredQty = $qty;
                    } else {
                        $restoredQty = min($qty, (int) $batch->reserved_quantity);
                        if ($restoredQty <= 0) {
                            continue;
                        }
                        $batch->reserved_quantity = max(0, (int) $batch->reserved_quantity - $restoredQty);
                    }

                    $batch->save();
                    $item->variant->unsetRelation('batches');

                    InventoryMovement::query()->create([
                        'product_variant_id' => $item->variant->id,
                        'product_batch_id' => $batch->id,
                        'warehouse_location_id' => $batch->warehouse_location_id,
                        'user_id' => $actor?->id,
                        'movement_type' => 'liberacion',
                        'quantity' => $restoredQty,
                        'balance_after' => $item->variant->availableStock(),
                        'unit_cost' => $batch->unit_cost,
                        'reference_type' => Order::class,
                        'reference_id' => $lockedOrder->id,
                        'notes' => "Liberación FEFO por cancelación de pedido {$lockedOrder->order_number} (lote {$batch->batch_number}).",
                    ]);
                }
            }
        });
    }

    /**
     * Reintegra unidades al lote original (o primer lote activo) cuando un RMA es apto para reventa.
     */
    public function restockFromReturn(OrderReturn $orderReturn, ReturnItem $returnItem, ?User $actor = null): void
    {
        if ($returnItem->condition !== ReturnCondition::Reventa) {
            throw new InvalidArgumentException('Solo los ítems evaluados como "Apto para reventa" pueden reintegrarse al inventario.');
        }

        if ($returnItem->restocked) {
            return;
        }

        DB::transaction(function () use ($orderReturn, $returnItem, $actor): void {
            /** @var ReturnItem $lockedReturnItem */
            $lockedReturnItem = ReturnItem::query()
                ->with(['variant', 'orderItem'])
                ->lockForUpdate()
                ->findOrFail($returnItem->id);

            if ($lockedReturnItem->restocked) {
                return;
            }

            $today = now()->toDateString();

            $batchId = $lockedReturnItem->product_batch_id
                ?? $lockedReturnItem->orderItem?->product_batch_id
                ?? ($lockedReturnItem->orderItem?->batch_allocations[0]['batch_id'] ?? null);

            $batch = $batchId !== null
                ? ProductBatch::query()->lockForUpdate()->find((int) $batchId)
                : null;

            if (
                $batch instanceof ProductBatch
                && (
                    ! in_array($batch->status, ['active', 'depleted'], true)
                    || $batch->expiration_date === null
                    || $batch->expiration_date->toDateString() <= $today
                )
            ) {
                $batch = null;
            }

            if (! $batch instanceof ProductBatch && $lockedReturnItem->product_variant_id !== null) {
                $batch = ProductBatch::query()
                    ->where('product_variant_id', $lockedReturnItem->product_variant_id)
                    ->whereIn('status', ['active', 'depleted'])
                    ->whereDate('expiration_date', '>', $today)
                    ->orderBy('expiration_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $batch instanceof ProductBatch || ! $lockedReturnItem->variant instanceof ProductVariant) {
                throw new InsufficientFefoStockException(
                    "No se encontró un lote vigente para reintegrar el ítem RMA #{$lockedReturnItem->id}."
                );
            }

            $qty = max(1, (int) $lockedReturnItem->quantity);

            $batch->current_quantity = (int) $batch->current_quantity + $qty;
            if (
                $batch->status === 'depleted'
                && $batch->expiration_date !== null
                && $batch->expiration_date->toDateString() > $today
            ) {
                $batch->status = 'active';
            }
            $batch->save();

            $lockedReturnItem->product_batch_id = $batch->id;
            $lockedReturnItem->restocked = true;
            $lockedReturnItem->save();

            $returnItem->forceFill([
                'product_batch_id' => $batch->id,
                'restocked' => true,
            ])->syncOriginal();

            $lockedReturnItem->variant->unsetRelation('batches');

            InventoryMovement::query()->create([
                'product_variant_id' => $lockedReturnItem->product_variant_id,
                'product_batch_id' => $batch->id,
                'warehouse_location_id' => $batch->warehouse_location_id,
                'user_id' => $actor?->id,
                'movement_type' => 'devolucion',
                'quantity' => $qty,
                'balance_after' => $lockedReturnItem->variant->availableStock(),
                'unit_cost' => $batch->unit_cost,
                'reference_type' => OrderReturn::class,
                'reference_id' => $orderReturn->id,
                'notes' => "Reingreso por devolución {$orderReturn->rma_number} apta para reventa (lote {$batch->batch_number}).",
            ]);
        });
    }

    /**
     * @return list<array{batch_id: int, quantity: int}>
     */
    private function extractItemAllocations(OrderItem $item): array
    {
        $allocations = [];

        if (is_array($item->batch_allocations) && $item->batch_allocations !== []) {
            foreach ($item->batch_allocations as $row) {
                if (! is_array($row) || ! isset($row['batch_id'], $row['quantity'])) {
                    continue;
                }

                $allocations[] = [
                    'batch_id' => (int) $row['batch_id'],
                    'quantity' => (int) $row['quantity'],
                ];
            }

            return $allocations;
        }

        if ($item->product_batch_id !== null && $item->quantity > 0) {
            return [[
                'batch_id' => (int) $item->product_batch_id,
                'quantity' => (int) $item->quantity,
            ]];
        }

        return [];
    }
}
