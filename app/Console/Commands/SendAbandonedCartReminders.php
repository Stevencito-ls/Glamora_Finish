<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\SecurityActionTriggered;
use App\Services\AbandonedCartRecoveryService;
use Illuminate\Console\Command;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'glamora:send-abandoned-carts {--minutes=60} {--max-reminders=3} {--cooldown-hours=24} {--dry-run}';

    protected $description = 'Envía recordatorios por correo en cola para carritos abandonados elegibles.';

    public function handle(AbandonedCartRecoveryService $recoveryService): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $maxReminders = max(1, (int) $this->option('max-reminders'));
        $cooldownHours = max(0, (int) $this->option('cooldown-hours'));
        $dryRun = (bool) $this->option('dry-run');

        $result = $recoveryService->processEligible(
            minutesIdle: $minutes,
            maxReminders: $maxReminders,
            cooldownHours: $cooldownHours,
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->info("[DRY-RUN] Carritos elegibles encontrados: {$result['eligible']} (no se encolaron correos ni se modificó la base de datos).");

            return self::SUCCESS;
        }

        if ($result['queued'] > 0) {
            SecurityActionTriggered::dispatch(
                null,
                'abandoned_cart_reminders_dispatched',
                'info',
                "Se encolaron {$result['queued']} recordatorio(s) de carritos abandonados vía CLI.",
                [
                    'eligible' => $result['eligible'],
                    'queued' => $result['queued'],
                    'skipped' => $result['skipped'],
                    'cart_ids' => $result['cart_ids'],
                    'minutes' => $minutes,
                    'max_reminders' => $maxReminders,
                    'cooldown_hours' => $cooldownHours,
                ],
            );
        }

        $this->info("Proceso completado. Elegibles: {$result['eligible']} | Encolados: {$result['queued']} | Omitidos: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
