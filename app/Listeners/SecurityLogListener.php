<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SecurityActionTriggered;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;

class SecurityLogListener
{
    public function handleLogin(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->record(
            userId: $user?->id,
            eventType: 'auth_login',
            severity: 'info',
            description: $user !== null
                ? "Inicio de sesión exitoso de {$user->email}."
                : 'Inicio de sesión exitoso.',
            metadata: [
                'guard' => $event->guard,
                'remember' => $event->remember,
                'two_factor_enabled' => (bool) ($user?->two_factor_enabled ?? false),
            ],
        );
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->record(
            userId: $user?->id,
            eventType: 'auth_logout',
            severity: 'info',
            description: $user !== null
                ? "Cierre de sesión de {$user->email}."
                : 'Cierre de sesión.',
            metadata: [
                'guard' => $event->guard,
            ],
        );
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $attemptedEmail = isset($event->credentials['email'])
            ? mb_strtolower(trim((string) $event->credentials['email']))
            : null;

        $this->record(
            userId: $user?->id,
            eventType: 'auth_failed',
            severity: 'warning',
            description: $attemptedEmail !== null
                ? "Intento de inicio de sesión fallido para {$attemptedEmail}."
                : 'Intento de inicio de sesión fallido.',
            metadata: array_filter([
                'guard' => $event->guard,
                'attempted_email' => $attemptedEmail,
            ], static fn (mixed $v): bool => $v !== null),
        );
    }

    public function handleRegistered(Registered $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->record(
            userId: $user?->id,
            eventType: 'auth_registered',
            severity: 'info',
            description: $user !== null
                ? "Nuevo registro de cuenta cliente: {$user->email}."
                : 'Nuevo registro de usuario.',
            metadata: [
                'document_type' => $user?->document_type,
                'customer_tier' => $user?->customer_tier?->value ?? 'minorista',
            ],
        );
    }

    public function handleSecurityAction(SecurityActionTriggered $event): void
    {
        $this->record(
            userId: $event->user?->id,
            eventType: $event->eventType,
            severity: $event->severity,
            description: $event->description,
            metadata: $event->metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        ?int $userId,
        string $eventType,
        string $severity,
        string $description,
        array $metadata = [],
    ): void {
        $request = request();

        SecurityLog::query()->create([
            'user_id' => $userId,
            'event_type' => mb_substr($eventType, 0, 60),
            'severity' => in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'info',
            'ip_address' => $request->ip() ? mb_substr((string) $request->ip(), 0, 45) : null,
            'user_agent' => $request->userAgent() ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            'description' => mb_substr($description, 0, 255),
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }
}
