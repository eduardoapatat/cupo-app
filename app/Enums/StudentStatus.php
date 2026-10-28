<?php

namespace App\Enums;

use App\Models\User;

/**
 * Whether a student uses the app. Enrolled at the counter she has no
 * password; with an email she got the link to activate her account.
 */
enum StudentStatus: string
{
    case App = 'app';
    case ActivationSent = 'activation_sent';
    case NoApp = 'no_app';

    public static function for(User $user): self
    {
        return match (true) {
            $user->usesApp() => self::App,
            $user->email !== null => self::ActivationSent,
            default => self::NoApp,
        };
    }

    /**
     * The value in the product URL (`?estado=sin-app`), in Spanish.
     */
    public function queryValue(): string
    {
        return match ($this) {
            self::App => 'con-app',
            self::ActivationSent => 'activacion-enviada',
            self::NoApp => 'sin-app',
        };
    }

    /**
     * An unknown value means no filter.
     */
    public static function fromQuery(?string $value): ?self
    {
        foreach (self::cases() as $status) {
            if ($status->queryValue() === $value) {
                return $status;
            }
        }

        return null;
    }

    /**
     * The condition on `users` of this status, for the filter and the
     * counts per tab.
     *
     * @return literal-string
     */
    public function condition(): string
    {
        return match ($this) {
            self::App => 'users.password is not null',
            self::ActivationSent => 'users.password is null and users.email is not null',
            self::NoApp => 'users.password is null and users.email is null',
        };
    }
}
