<?php

namespace App\Platform\Audit\Listeners;

use App\Platform\Audit\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Entrar, sair, senha e verificação em duas etapas viram linhas da
 * auditoria. A falha ao entrar guarda o usuário só quando o e-mail existe,
 * e nunca o e-mail digitado.
 */
class RecordAuthActivity
{
    public function __construct(private readonly Audit $audit) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Logout::class => 'logout',
            Failed::class => 'failed',
            PasswordReset::class => 'passwordReset',
            PasswordUpdatedViaController::class => 'passwordChanged',
            TwoFactorAuthenticationConfirmed::class => 'twoFactorEnabled',
            TwoFactorAuthenticationDisabled::class => 'twoFactorDisabled',
            TwoFactorAuthenticationFailed::class => 'twoFactorFailed',
            RecoveryCodesGenerated::class => 'recoveryCodesGenerated',
        ];
    }

    public function login(Login $event): void
    {
        $this->audit->record('auth.login', userId: $event->user->getAuthIdentifier(), meta: [
            'remember' => $event->remember,
        ]);
    }

    public function logout(Logout $event): void
    {
        if ($event->user !== null) {
            $this->audit->record('auth.logout', userId: $event->user->getAuthIdentifier());
        }
    }

    public function failed(Failed $event): void
    {
        $this->audit->record('auth.failed', userId: $event->user?->getAuthIdentifier(), meta: [
            'known_user' => $event->user !== null,
        ]);
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->audit->record('auth.password_reset', userId: $event->user->getAuthIdentifier());
    }

    public function passwordChanged(PasswordUpdatedViaController $event): void
    {
        $this->audit->record('auth.password_changed', userId: $event->user->getAuthIdentifier());
    }

    public function twoFactorEnabled(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->audit->record('auth.two_factor_enabled', userId: $event->user->getAuthIdentifier());
    }

    public function twoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->audit->record('auth.two_factor_disabled', userId: $event->user->getAuthIdentifier());
    }

    public function twoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->audit->record('auth.two_factor_failed', userId: $event->user->getAuthIdentifier());
    }

    public function recoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->audit->record('auth.recovery_codes_generated', userId: $event->user->getAuthIdentifier());
    }
}
