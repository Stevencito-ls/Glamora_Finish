<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\AbandonedCartReminderMail;
use App\Models\AbandonedCart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AbandonedCartRecoveryService
{
    public function eligibleQuery(int $minutesIdle = 60, int $maxReminders = 3, int $cooldownHours = 24): Builder
    {
        $minutesIdle = max(1, $minutesIdle);
        $maxReminders = max(1, $maxReminders);
        $cooldownThreshold = now()->subHours(max(0, $cooldownHours));

        return AbandonedCart::query()
            ->eligibleForReminder($minutesIdle)
            ->where('email', '!=', '')
            ->where('reminder_count', '<', $maxReminders)
            ->where(function (Builder $query) use ($cooldownThreshold): void {
                $query->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<=', $cooldownThreshold);
            })
            ->orderBy('last_activity_at')
            ->orderBy('id');
    }

    public function eligibleCount(int $minutesIdle = 60, int $maxReminders = 3, int $cooldownHours = 24): int
    {
        return (int) $this->eligibleQuery($minutesIdle, $maxReminders, $cooldownHours)->count();
    }

    /**
     * @return array{
     *     eligible: int,
     *     queued: int,
     *     skipped: int,
     *     dry_run: bool,
     *     cart_ids: list<int>
     * }
     */
    public function processEligible(
        int $minutesIdle = 60,
        int $maxReminders = 3,
        int $cooldownHours = 24,
        bool $dryRun = false,
    ): array {
        $candidateIds = $this->eligibleQuery($minutesIdle, $maxReminders, $cooldownHours)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($dryRun) {
            return [
                'eligible' => count($candidateIds),
                'queued' => 0,
                'skipped' => 0,
                'dry_run' => true,
                'cart_ids' => $candidateIds,
            ];
        }

        $queuedIds = [];
        $skipped = 0;

        foreach ($candidateIds as $cartId) {
            $queued = DB::transaction(function () use ($cartId, $minutesIdle, $maxReminders, $cooldownHours): bool {
                /** @var AbandonedCart|null $lockedCart */
                $lockedCart = AbandonedCart::query()
                    ->with('user')
                    ->lockForUpdate()
                    ->find($cartId);

                if (! $lockedCart instanceof AbandonedCart || ! $this->isCartEligible($lockedCart, $minutesIdle, $maxReminders, $cooldownHours)) {
                    return false;
                }

                $recipient = trim((string) $lockedCart->email);
                if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                    return false;
                }

                $nextStep = (int) $lockedCart->reminder_count + 1;

                Mail::to($recipient)->queue(new AbandonedCartReminderMail($lockedCart, $nextStep));

                $lockedCart->forceFill([
                    'reminder_count' => $nextStep,
                    'last_reminded_at' => now(),
                ])->save();

                return true;
            });

            if ($queued) {
                $queuedIds[] = $cartId;
            } else {
                $skipped++;
            }
        }

        return [
            'eligible' => count($candidateIds),
            'queued' => count($queuedIds),
            'skipped' => $skipped,
            'dry_run' => false,
            'cart_ids' => $queuedIds,
        ];
    }

    public function sendSingleReminder(AbandonedCart $cart, bool $ignoreCooldown = true): bool
    {
        return DB::transaction(function () use ($cart, $ignoreCooldown): bool {
            /** @var AbandonedCart|null $lockedCart */
            $lockedCart = AbandonedCart::query()
                ->with('user')
                ->lockForUpdate()
                ->find($cart->id);

            if (! $lockedCart instanceof AbandonedCart || $lockedCart->recovered_at !== null || (int) $lockedCart->items_count <= 0) {
                return false;
            }

            if (! $ignoreCooldown && ! $this->isCartEligible($lockedCart, 60, 3, 24)) {
                return false;
            }

            $recipient = trim((string) $lockedCart->email);
            if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }

            $nextStep = (int) $lockedCart->reminder_count + 1;

            Mail::to($recipient)->queue(new AbandonedCartReminderMail($lockedCart, $nextStep));

            $lockedCart->forceFill([
                'reminder_count' => $nextStep,
                'last_reminded_at' => now(),
            ])->save();

            return true;
        });
    }

    private function isCartEligible(AbandonedCart $cart, int $minutesIdle, int $maxReminders, int $cooldownHours): bool
    {
        if ($cart->recovered_at !== null || (int) $cart->items_count <= 0) {
            return false;
        }

        if ($cart->last_activity_at === null || $cart->last_activity_at->isAfter(now()->subMinutes(max(1, $minutesIdle)))) {
            return false;
        }

        if ((int) $cart->reminder_count >= max(1, $maxReminders)) {
            return false;
        }

        if ($cart->last_reminded_at !== null && $cart->last_reminded_at->isAfter(now()->subHours(max(0, $cooldownHours)))) {
            return false;
        }

        return true;
    }
}
