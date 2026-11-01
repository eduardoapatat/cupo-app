<?php

namespace App\Enums;

/**
 * How a student paid. A card at the counter goes through the studio's own
 * POS terminal, and the app only records it; Webpay is paid online, from
 * the app.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Webpay = 'webpay';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Card => 'Tarjeta',
            self::Transfer => 'Transferencia',
            self::Webpay => 'Webpay',
        };
    }

    /**
     * The methods the front desk takes at the counter.
     *
     * @return array<int, self>
     */
    public static function atTheCounter(): array
    {
        return [self::Cash, self::Card, self::Transfer];
    }
}
