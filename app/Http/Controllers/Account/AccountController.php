<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ReturnItem;
use App\Models\SecurityLog;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\TotpService;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function index(): View
    {
        $user = $this->currentUser();
        $user->load(['wholesaleProfile', 'addresses']);

        $recentOrders = $user->orders()
            ->with(['payments', 'items'])
            ->latest()
            ->limit(5)
            ->get();

        $ordersCount = $user->orders()->count();
        $pendingOrdersCount = $user->orders()
            ->where('status', OrderStatus::PendientePago->value)
            ->count();
        $activeReturnsCount = $user->returns()
            ->whereIn('status', ['solicitado', 'en_revision'])
            ->count();

        $alerts = [];
        if (! $user->two_factor_enabled) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Protege tu cuenta con 2FA TOTP',
                'message' => 'Activa la verificación en dos pasos en la pestaña Seguridad para resguardar tus pedidos y créditos B2B.',
                'action_label' => 'Configurar 2FA',
                'action_url' => route('account.security'),
            ];
        }

        if ($user->wholesaleProfile !== null && $user->wholesaleProfile->status === 'pending') {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Solicitud Mayorista B2B en evaluación',
                'message' => "Tu solicitud para {$user->wholesaleProfile->company_name} (RUC {$user->wholesaleProfile->ruc}) está siendo revisada por nuestro equipo comercial.",
                'action_label' => 'Ver Portal Mayorista',
                'action_url' => route('wholesale.index'),
            ];
        }

        if ($pendingOrdersCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => "Tienes {$pendingOrdersCount} pedido(s) pendiente(s) de pago o conciliación",
                'message' => 'Revisa el detalle en Mis Pedidos para hacer seguimiento del estado de verificación.',
                'action_label' => 'Ver Pedidos',
                'action_url' => route('account.orders'),
            ];
        }

        return view('account.index', [
            'user' => $user,
            'effectiveTier' => $user->effectiveTier(),
            'wholesaleProfile' => $user->wholesaleProfile,
            'recentOrders' => $recentOrders,
            'ordersCount' => $ordersCount,
            'addressesCount' => $user->addresses->count(),
            'activeReturnsCount' => $activeReturnsCount,
            'alerts' => $alerts,
        ]);
    }

    public function orders(): View
    {
        $user = $this->currentUser();
        $orders = $user->orders()
            ->with(['items', 'payments'])
            ->latest()
            ->paginate(10);

        return view('account.orders', [
            'user' => $user,
            'orders' => $orders,
        ]);
    }

    public function showOrder(string $orderNumber): View
    {
        $order = $this->resolveOwnedOrder($orderNumber);

        return view('account.order-show', [
            'user' => $this->currentUser(),
            'order' => $order,
        ]);
    }

    public function orderPdf(string $orderNumber): Response
    {
        $order = $this->resolveOwnedOrder($orderNumber);

        $pdf = Pdf::loadView('pdf.order', [
            'order' => $order,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Pedido-Glamora-{$order->order_number}.pdf");
    }

    public function addresses(): View
    {
        $user = $this->currentUser();
        $addresses = $user->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return view('account.addresses', [
            'user' => $user,
            'addresses' => $addresses,
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:60'],
            'recipient_name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['required', 'string', 'min:7', 'max:25'],
            'address_line' => ['required', 'string', 'min:5', 'max:255'],
            'district' => ['required', 'string', 'min:2', 'max:100'],
            'city' => ['required', 'string', 'min:2', 'max:100'],
            'reference' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'label.required' => 'Ingresa una etiqueta para identificar la dirección (ej. Casa, Salón).',
            'recipient_name.required' => 'Ingresa el nombre de quien recibe el pedido.',
            'phone.required' => 'Ingresa un número telefónico de entrega.',
            'address_line.required' => 'Ingresa la dirección completa.',
            'district.required' => 'Ingresa el distrito.',
            'city.required' => 'Ingresa la ciudad.',
        ]);

        $user = $this->currentUser();
        $makeDefault = $request->boolean('is_default') || $user->addresses()->count() === 0;

        DB::transaction(function () use ($user, $validated, $makeDefault): void {
            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            UserAddress::query()->create([
                'user_id' => $user->id,
                'label' => trim($validated['label']),
                'recipient_name' => trim($validated['recipient_name']),
                'phone' => trim($validated['phone']),
                'address_line' => trim($validated['address_line']),
                'district' => trim($validated['district']),
                'city' => trim($validated['city']),
                'reference' => isset($validated['reference']) && trim((string) $validated['reference']) !== ''
                    ? trim((string) $validated['reference'])
                    : null,
                'is_default' => $makeDefault,
            ]);

            if ($makeDefault) {
                $user->fill([
                    'default_address' => trim($validated['address_line']),
                    'district' => trim($validated['district']),
                    'city' => trim($validated['city']),
                ])->save();
            }
        });

        return redirect()
            ->route('account.addresses')
            ->with('account_success', 'Dirección guardada en tu libreta de entregas.');
    }

    public function setDefaultAddress(UserAddress $address): RedirectResponse
    {
        $user = $this->currentUser();
        abort_unless($address->user_id === $user->id, 403);

        DB::transaction(function () use ($user, $address): void {
            $user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            $user->fill([
                'default_address' => $address->address_line,
                'district' => $address->district,
                'city' => $address->city,
            ])->save();
        });

        return redirect()
            ->route('account.addresses')
            ->with('account_success', "«{$address->label}» marcada como dirección predeterminada.");
    }

    public function destroyAddress(UserAddress $address): RedirectResponse
    {
        $user = $this->currentUser();
        abort_unless($address->user_id === $user->id, 403);

        DB::transaction(function () use ($user, $address): void {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $next = $user->addresses()->latest()->first();
                if ($next instanceof UserAddress) {
                    $next->update(['is_default' => true]);
                    $user->fill([
                        'default_address' => $next->address_line,
                        'district' => $next->district,
                        'city' => $next->city,
                    ])->save();
                }
            }
        });

        return redirect()
            ->route('account.addresses')
            ->with('account_success', 'Dirección eliminada correctamente.');
    }

    public function returns(): View
    {
        $user = $this->currentUser();

        $returns = OrderReturn::query()
            ->with(['order', 'items.orderItem'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $eligibleOrders = $user->orders()
            ->with(['items.returnItems.orderReturn'])
            ->whereNotIn('status', [
                OrderStatus::Cotizacion->value,
                OrderStatus::PendientePago->value,
                OrderStatus::Cancelado->value,
            ])
            ->latest()
            ->get()
            ->map(function (Order $order): array {
                $returnableItems = $order->items
                    ->map(function (OrderItem $item): ?array {
                        $maxReturnable = $this->maxReturnableQuantity($item);
                        if ($maxReturnable < 1) {
                            return null;
                        }

                        return [
                            'id' => $item->id,
                            'product_name' => $item->product_name,
                            'variant_name' => $item->variant_name,
                            'sku' => $item->sku,
                            'purchased_quantity' => $item->quantity,
                            'max_returnable' => $maxReturnable,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'order' => $order,
                    'returnable_items' => $returnableItems,
                ];
            })
            ->filter(fn (array $entry): bool => $entry['returnable_items'] !== [])
            ->values();

        return view('account.returns', [
            'user' => $user,
            'returns' => $returns,
            'eligibleOrders' => $eligibleOrders,
        ]);
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.selected' => ['nullable'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'order_number.required' => 'Selecciona el pedido sobre el cual deseas solicitar la devolución RMA.',
            'reason.required' => 'Describe el motivo de la devolución.',
            'reason.min' => 'El motivo debe tener al menos 10 caracteres.',
        ]);

        $user = $this->currentUser();
        $orderCode = strtoupper(trim($validated['order_number']));

        /** @var OrderReturn $orderReturn */
        $orderReturn = DB::transaction(function () use ($user, $orderCode, $validated): OrderReturn {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->whereRaw('UPPER(order_number) = ?', [$orderCode])
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Order) {
                throw ValidationException::withMessages([
                    'order_number' => "No encontramos el pedido {$orderCode} asociado a tu cuenta.",
                ]);
            }

            if (in_array($order->status, [OrderStatus::Cotizacion, OrderStatus::PendientePago, OrderStatus::Cancelado], true)) {
                throw ValidationException::withMessages([
                    'order_number' => "El pedido {$order->order_number} en estado «{$order->status->label()}» no es elegible para devolución.",
                ]);
            }

            $order->load(['items.returnItems.orderReturn']);
            $orderItemsById = $order->items->keyBy('id');
            $selectedLines = [];

            if (isset($validated['items']) && is_array($validated['items'])) {
                $hadSelectedMismatch = false;

                foreach ($validated['items'] as $orderItemId => $row) {
                    if (! is_array($row) || empty($row['selected'])) {
                        continue;
                    }

                    $orderItem = $orderItemsById->get((int) $orderItemId);
                    if (! $orderItem instanceof OrderItem) {
                        $hadSelectedMismatch = true;
                        continue;
                    }

                    $maxReturnable = $this->maxReturnableQuantity($orderItem);
                    $qty = (int) ($row['quantity'] ?? 1);

                    if ($maxReturnable < 1 || $qty < 1 || $qty > $maxReturnable) {
                        throw ValidationException::withMessages([
                            'items' => "Cantidad inválida para {$orderItem->sku} (máximo disponible: {$maxReturnable}).",
                        ]);
                    }

                    $selectedLines[] = [
                        'order_item' => $orderItem,
                        'quantity' => $qty,
                    ];
                }

                if ($hadSelectedMismatch) {
                    throw ValidationException::withMessages([
                        'items' => 'Los productos seleccionados no corresponden al pedido indicado.',
                    ]);
                }

                if ($selectedLines === []) {
                    throw ValidationException::withMessages([
                        'items' => 'Selecciona al menos un producto válido para solicitar la devolución RMA.',
                    ]);
                }
            } else {
                $firstReturnable = $order->items->first(fn (OrderItem $item): bool => $this->maxReturnableQuantity($item) > 0);
                if (! $firstReturnable instanceof OrderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Este pedido no tiene unidades pendientes disponibles para devolución.',
                    ]);
                }

                $selectedLines[] = [
                    'order_item' => $firstReturnable,
                    'quantity' => 1,
                ];
            }

            $rmaNumber = $this->generateUniqueRmaNumber();

            $rma = OrderReturn::query()->create([
                'rma_number' => $rmaNumber,
                'order_id' => $order->id,
                'user_id' => $user->id,
                'requester_name' => $user->name,
                'requester_email' => $user->email,
                'reason' => trim($validated['reason']),
                'status' => 'solicitado',
                'refund_amount' => 0.00,
            ]);

            foreach ($selectedLines as $line) {
                /** @var OrderItem $orderItem */
                $orderItem = $line['order_item'];

                ReturnItem::query()->create([
                    'return_id' => $rma->id,
                    'order_item_id' => $orderItem->id,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'product_batch_id' => $orderItem->product_batch_id,
                    'quantity' => $line['quantity'],
                    'condition' => null,
                    'restocked' => false,
                ]);
            }

            SecurityActionTriggered::dispatch(
                $user,
                'rma_requested',
                'info',
                "Solicitud RMA {$rma->rma_number} registrada para el pedido {$order->order_number}.",
                ['rma_number' => $rma->rma_number, 'order_number' => $order->order_number],
            );

            return $rma;
        });

        return redirect()
            ->route('account.returns')
            ->with('account_success', "Solicitud RMA {$orderReturn->rma_number} registrada correctamente para inspección sanitaria.");
    }

    public function security(TotpService $totpService): View
    {
        $user = $this->currentUser();
        $logs = SecurityLog::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(15)
            ->get();

        $pendingTotpUri = null;
        if (! $user->two_factor_enabled && is_string($user->two_factor_secret) && $user->two_factor_secret !== '') {
            $pendingTotpUri = $totpService->getProvisioningUri($user->email, $user->two_factor_secret);
        }

        return view('account.security', [
            'user' => $user,
            'logs' => $logs,
            'pendingTotpUri' => $pendingTotpUri,
            'recoveryCodesCount' => is_array($user->two_factor_recovery_codes) ? count($user->two_factor_recovery_codes) : 0,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['required', 'string', 'min:7', 'max:25'],
            'document_type' => ['required', 'string', Rule::in(['DNI', 'RUC', 'CE'])],
            'document_number' => [
                'required',
                'string',
                'min:6',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $docType = (string) $request->input('document_type', 'DNI');
                    $clean = trim((string) $value);

                    if ($docType === 'DNI' && preg_match('/^\d{8}$/', $clean) !== 1) {
                        $fail('El DNI debe contener exactamente 8 dígitos numéricos.');
                    } elseif ($docType === 'RUC' && preg_match('/^\d{11}$/', $clean) !== 1) {
                        $fail('El RUC debe contener exactamente 11 dígitos numéricos.');
                    } elseif ($docType === 'CE' && preg_match('/^[A-Za-z0-9\-]{6,20}$/', $clean) !== 1) {
                        $fail('El Carné de Extranjería debe tener entre 6 y 20 caracteres alfanuméricos.');
                    }
                },
            ],
            'default_address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
        ], [
            'name.required' => 'Ingresa tu nombre completo.',
            'phone.required' => 'Ingresa un número de teléfono.',
            'document_number.required' => 'Ingresa tu número de documento.',
        ]);

        $user = $this->currentUser();
        $user->fill([
            'name' => trim($validated['name']),
            'phone' => trim($validated['phone']),
            'document_type' => $validated['document_type'],
            'document_number' => trim($validated['document_number']),
            'default_address' => isset($validated['default_address']) ? trim((string) $validated['default_address']) : null,
            'district' => isset($validated['district']) ? trim((string) $validated['district']) : null,
            'city' => isset($validated['city']) && trim((string) $validated['city']) !== '' ? trim((string) $validated['city']) : 'Lima',
        ])->save();

        SecurityActionTriggered::dispatch(
            $user,
            'profile_updated',
            'info',
            "Datos de perfil actualizados por {$user->email}.",
        );

        return redirect()
            ->route('account.security')
            ->with('account_success', 'Información personal actualizada correctamente.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
        ]);

        $user = $this->currentUser();
        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        $request->session()->regenerate();

        SecurityActionTriggered::dispatch(
            $user,
            'password_changed',
            'warning',
            "Contraseña actualizada desde el portal de seguridad por {$user->email}.",
        );

        return redirect()
            ->route('account.security')
            ->with('account_success', 'Contraseña actualizada correctamente.');
    }

    public function enableTwoFactor(TotpService $totpService): RedirectResponse
    {
        $user = $this->currentUser();

        if ($user->two_factor_enabled) {
            return redirect()
                ->route('account.security')
                ->with('account_success', 'La autenticación en dos pasos (2FA) ya está activa en tu cuenta.');
        }

        $secret = $totpService->generateSecret(32);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ])->save();

        SecurityActionTriggered::dispatch(
            $user,
            'two_factor_setup_initiated',
            'info',
            "Inicio de configuración 2FA TOTP para {$user->email}.",
        );

        return redirect()
            ->route('account.security')
            ->with('account_success', 'Secreto TOTP generado. Regístralo en tu app autenticadora y confirma con el código de 6 dígitos.');
    }

    public function confirmTwoFactor(Request $request, TotpService $totpService): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Ingresa el código de 6 dígitos de tu app autenticadora.',
            'code.digits' => 'El código TOTP debe tener exactamente 6 dígitos.',
        ]);

        $user = $this->currentUser();

        if (! is_string($user->two_factor_secret) || $user->two_factor_secret === '') {
            return redirect()
                ->route('account.security')
                ->withErrors(['code' => 'Primero debes generar un secreto 2FA antes de confirmar.']);
        }

        if (! $totpService->verifyCode($user->two_factor_secret, $validated['code'], window: 1)) {
            return redirect()
                ->route('account.security')
                ->withErrors(['code' => 'El código de verificación 2FA no es válido. Verifica la hora de tu dispositivo e intenta nuevamente.']);
        }

        $recoveryCodes = $totpService->generateRecoveryCodes(8);

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        SecurityActionTriggered::dispatch(
            $user,
            'two_factor_enabled',
            'info',
            "Autenticación en dos pasos (2FA TOTP) activada para {$user->email}.",
        );

        return redirect()
            ->route('account.security')
            ->with('account_success', 'Autenticación en dos pasos (2FA) activada correctamente. Guarda tus 8 códigos de recuperación.')
            ->with('two_factor_recovery_codes', $recoveryCodes);
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.required' => 'Ingresa tu contraseña actual para desactivar el 2FA.',
            'current_password.current_password' => 'La contraseña actual no coincide.',
        ]);

        $user = $this->currentUser();

        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        SecurityActionTriggered::dispatch(
            $user,
            'two_factor_disabled',
            'warning',
            "Autenticación en dos pasos (2FA) desactivada por {$user->email}.",
        );

        return redirect()
            ->route('account.security')
            ->with('account_success', 'Autenticación en dos pasos (2FA) desactivada.');
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function resolveOwnedOrder(string $orderNumber): Order
    {
        $user = $this->currentUser();
        $normalized = strtoupper(trim($orderNumber));

        $order = Order::query()
            ->with(['items.variant.product', 'payments', 'returns.items.orderItem'])
            ->whereRaw('UPPER(order_number) = ?', [$normalized])
            ->firstOrFail();

        abort_unless($order->user_id === $user->id, 403);

        return $order;
    }

    private function maxReturnableQuantity(OrderItem $orderItem): int
    {
        $alreadyRequested = (int) $orderItem->returnItems
            ->filter(fn (ReturnItem $ri): bool => ! in_array($ri->orderReturn?->status, ['rechazado', 'cancelado'], true))
            ->sum('quantity');

        return max(0, (int) $orderItem->quantity - $alreadyRequested);
    }

    private function generateUniqueRmaNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "RMA-{$year}-";

        $latest = OrderReturn::query()
            ->where('rma_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('rma_number');

        $nextNumber = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) === 1) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $exists = OrderReturn::query()->where('rma_number', $candidate)->exists();
            $nextNumber++;
        } while ($exists);

        return $candidate;
    }
}
