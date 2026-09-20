<?php

namespace App\Support;

use App\Enums\Currency;
use App\Models\SystemSetting;
use ArPHP\I18N\Arabic;

final class ArabicMoneyWords
{
    /**
     * Spell a monetary amount in Arabic, including the system currency name.
     */
    public static function from(mixed $amount): string
    {
        $iso = self::isoCode();
        $formatted = bcadd((string) ($amount ?? 0), '0', 2);

        if (bccomp($formatted, '0', 2) === 0) {
            return match ($iso) {
                'IQD' => 'صفر دينار',
                default => 'صفر دولار',
            };
        }

        $words = (new Arabic)->money2str($formatted, $iso, 'ar');
        $integer = explode('.', $formatted)[0];

        if ($iso === 'USD' && $integer === '2') {
            $words = preg_replace('/^دولارات/u', 'دولاران', $words, 1) ?? $words;
        }

        return $words;
    }

    /**
     * Invoice/receipt phrase: "فقط … لا غير".
     */
    public static function phrase(mixed $amount): string
    {
        return self::from($amount).' فقط ';
    }

    private static function isoCode(): string
    {
        $currency = SystemSetting::current()->currency;

        return $currency instanceof Currency ? $currency->isoCode() : Currency::Usd->isoCode();
    }
}
