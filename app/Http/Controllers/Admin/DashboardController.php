<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use App\Models\WholesaleProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $isOpsStaff = $user->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero']);
        $today = now()->toDateString();

        $verifiedStatuses = [
            OrderStatus::PagoVerificado->value,
            OrderStatus::EnPicking->value,
            OrderStatus::EnPacking->value,
            OrderStatus::Despachado->value,
            OrderStatus::Entregado->value,
        ];

        $financialKpis = [
            'monthly_sales' => 0.0,
            'total_sales' => 0.0,
            'verified_orders_count' => 0,
            'monthly_orders_count' => 0,
            'estimated_margin' => 0.0,
            'margin_percent' => 0.0,
        ];

        if ($user->can('reports.view')) {
            $verifiedBase = Order::query()->whereIn('status', $verifiedStatuses);
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();

            $totalSales = (float) (clone $verifiedBase)->sum('total');
            $monthlySales = (float) (clone $verifiedBase)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('total');

            $verifiedOrdersCount = (int) (clone $verifiedBase)->count();
            $monthlyOrdersCount = (int) (clone $verifiedBase)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            // Net merchandise revenue (before shipping, after discount) vs COGS
            $netRevenue = (float) Order::query()
                ->whereIn('status', $verifiedStatuses)
                ->selectRaw('COALESCE(SUM(subtotal - discount_amount), 0) as net_revenue')
                ->value('net_revenue');

            $totalCogs = (float) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                ->whereIn('orders.status', $verifiedStatuses)
                ->selectRaw('COALESCE(SUM(order_items.quantity * COALESCE(product_variants.cost_price, 0)), 0) as total_cogs')
                ->value('total_cogs');

            $estimatedMargin = round($netRevenue - $totalCogs, 2);
            $marginPercent = $netRevenue > 0.0 ? round(($estimatedMargin / $netRevenue) * 100, 1) : 0.0;

            $financialKpis = [
                'monthly_sales' => round($monthlySales, 2),
                'total_sales' => round($totalSales, 2),
                'verified_orders_count' => $verifiedOrdersCount,
                'monthly_orders_count' => $monthlyOrdersCount,
                'estimated_margin' => $estimatedMargin,
                'margin_percent' => $marginPercent,
            ];
        }

        // Inventory & FEFO KPIs
        $inventoryKpis = [
            'current_available_stock' => 0,
            'units_out_30d' => 0,
            'average_stock' => 0.0,
            'turnover_ratio' => 0.0,
            'expiring_60_count' => 0,
            'critical_30_count' => 0,
            'low_stock_count' => 0,
            'reorder_alert_count' => 0,
        ];
        $expiringBatches = collect();
        $lowStockAlerts = collect();

        if ($user->can('inventory.view')) {
            $currentAvailableStock = (int) ProductBatch::query()
                ->available()
                ->reorder()
                ->selectRaw('COALESCE(SUM(CASE WHEN current_quantity > reserved_quantity THEN current_quantity - reserved_quantity ELSE 0 END), 0) as stock')
                ->value('stock');

            $unitsOut30d = (int) InventoryMovement::query()
                ->where('movement_type', 'salida')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('COALESCE(SUM(ABS(quantity)), 0) as total_out')
                ->value('total_out');

            $averageStock = (float) ProductBatch::query()
                ->whereIn('status', ['active', 'depleted'])
                ->selectRaw('COALESCE(SUM(initial_quantity + current_quantity) / 2.0, 0) as avg_stock')
                ->value('avg_stock');

            $turnoverRatio = $averageStock > 0.0 ? round($unitsOut30d / $averageStock, 2) : 0.0;

            $expiringBatches = ProductBatch::query()
                ->with(['variant.product', 'warehouseLocation'])
                ->expiringSoon(60)
                ->get();

            $expiring60Count = $expiringBatches->count();
            $critical30Count = $expiringBatches
                ->filter(fn (ProductBatch $batch): bool => $batch->daysUntilExpiration() <= 30)
                ->count();

            // Aggregate stock per product safely for ONLY_FULL_GROUP_BY
            $stockByProduct = DB::table('product_variants')
                ->leftJoin('product_batches', function ($join) use ($today): void {
                    $join->on('product_batches.product_variant_id', '=', 'product_variants.id')
                        ->where('product_batches.status', '=', 'active')
                        ->whereDate('product_batches.expiration_date', '>', $today)
                        ->whereColumn('product_batches.current_quantity', '>', 'product_batches.reserved_quantity');
                })
                ->where('product_variants.is_active', true)
                ->groupBy('product_variants.product_id')
                ->selectRaw('product_variants.product_id, COALESCE(SUM(CASE WHEN product_batches.current_quantity > product_batches.reserved_quantity THEN product_batches.current_quantity - product_batches.reserved_quantity ELSE 0 END), 0) as available_stock')
                ->pluck('available_stock', 'product_id');

            $activeProducts = Product::query()
                ->with(['category', 'brand'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $lowStockAlerts = $activeProducts
                ->map(function (Product $product) use ($stockByProduct): ?array {
                    $available = (int) ($stockByProduct[$product->id] ?? 0);
                    $isCritical = $available <= (int) $product->min_stock_alert;
                    $needsReorder = $available <= (int) $product->reorder_point;

                    if (! $isCritical && ! $needsReorder) {
                        return null;
                    }

                    return [
                        'product' => $product,
                        'available_stock' => $available,
                        'is_critical' => $isCritical,
                        'needs_reorder' => $needsReorder,
                    ];
                })
                ->filter()
                ->sortBy('available_stock')
                ->values();

            $inventoryKpis = [
                'current_available_stock' => $currentAvailableStock,
                'units_out_30d' => $unitsOut30d,
                'average_stock' => round($averageStock, 1),
                'turnover_ratio' => $turnoverRatio,
                'expiring_60_count' => $expiring60Count,
                'critical_30_count' => $critical30Count,
                'low_stock_count' => $lowStockAlerts->where('is_critical', true)->count(),
                'reorder_alert_count' => $lowStockAlerts->count(),
            ];
        }

        // Operational queues
        $ordersBase = Order::query();
        if (! $isOpsStaff) {
            $ordersBase->where('user_id', $user->id);
        }

        $pendingVerificationCount = $user->can('orders.view')
            ? (int) (clone $ordersBase)->where('status', OrderStatus::PendientePago->value)->count()
            : 0;

        $toDispatchCount = $user->can('orders.view')
            ? (int) (clone $ordersBase)->whereIn('status', [
                OrderStatus::PagoVerificado->value,
                OrderStatus::EnPicking->value,
                OrderStatus::EnPacking->value,
            ])->count()
            : 0;

        $recentActionableOrders = $user->can('orders.view')
            ? (clone $ordersBase)
                ->with(['payments'])
                ->whereIn('status', [
                    OrderStatus::PendientePago->value,
                    OrderStatus::PagoVerificado->value,
                    OrderStatus::EnPicking->value,
                    OrderStatus::EnPacking->value,
                ])
                ->latest()
                ->limit(6)
                ->get()
            : collect();

        $returnsBase = OrderReturn::query()->pendingInspection();
        if (! $isOpsStaff) {
            $returnsBase->where('user_id', $user->id);
        }
        $pendingRmasCount = $user->can('returns.view') ? (int) (clone $returnsBase)->count() : 0;

        $wholesaleBase = WholesaleProfile::query()->pending();
        if (! $user->hasAnyRole(['SuperAdmin', 'Administrador'])) {
            $wholesaleBase->where('user_id', $user->id);
        }
        $pendingWholesaleCount = $user->can('wholesale.view') ? (int) (clone $wholesaleBase)->count() : 0;

        return view('admin.dashboard', [
            'user' => $user,
            'isOpsStaff' => $isOpsStaff,
            'financialKpis' => $financialKpis,
            'inventoryKpis' => $inventoryKpis,
            'expiringBatches' => $expiringBatches->take(8),
            'lowStockAlerts' => $lowStockAlerts->take(8),
            'pendingVerificationCount' => $pendingVerificationCount,
            'toDispatchCount' => $toDispatchCount,
            'pendingRmasCount' => $pendingRmasCount,
            'pendingWholesaleCount' => $pendingWholesaleCount,
            'recentActionableOrders' => $recentActionableOrders,
        ]);
    }
}
