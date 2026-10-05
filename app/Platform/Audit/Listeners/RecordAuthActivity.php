<?php

namespace App\Platform\Audit\Listeners;

use App\Platform\Audit\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Entrar, sair, senha e verificação em duas etapas viram linhas da
 * auditoria, sempre com o login afetado como registro. Numa tentativa que
 * falhou, quem tentou é desconhecido: o login entra só como registro, quando
 * o e-mail existe, e o e-mail digitado nunca é guardado.
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
        $this->audit->record('auth.login', $this->account($event->user), userId: $event->user->getAuthIdentifier(), meta: [
            'remember' => $event->remember,
        ]);
    }

    public function logout(Logout $event): void
    {
        if ($event->user !== null) {
            $this->audit->record('auth.logout', $this->account($event->user), userId: $event->user->getAuthIdentifier());
        }
    }

    public function failed(Failed $event): void
    {
        $this->audit->record('auth.failed', $this->account($event->user), meta: [
            'known_user' => $event->user !== null,
        ]);
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->audit->record('auth.password_reset', $this->account($event->user), userId: $event->user->getAuthIdentifier());
    }

    public function passwordChanged(PasswordUpdatedViaController $event): void
    {
        $this->audit->record('auth.password_changed', $this->account($event->user), userId: $event->user->getAuthIdentifier());
    }

    public function twoFactorEnabled(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->audit->record('auth.two_factor_enabled', $this->account($event->user), userId: $event->user->getAuthIdentifier());
    }

    public function twoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->audit->record('auth.two_factor_disabled', $this->account($event->user), userId: $event->user->getAuthIdentifier());
    }

    public function twoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->audit->record('auth.two_factor_failed', $this->account($event->user));
    }

    public function recoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->audit->record('auth.recovery_codes_generated', $this->account($event->user), userId: $event->user->getAuthIdentifier());
    }

    private function account(mixed $user): ?Model
    {
        return $user instanceof Model ? $user : null;
    }
}
