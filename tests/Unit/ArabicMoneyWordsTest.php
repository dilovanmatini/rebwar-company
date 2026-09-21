<?php

use App\Enums\Currency;
use App\Models\SystemSetting;
use App\Support\ArabicMoneyWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('arabic money words spells usd amounts with currency name', function () {
    SystemSetting::current()->update(['currency' => Currency::Usd]);

    expect(ArabicMoneyWords::from(100))->toBe('مئة دولار')
        ->and(ArabicMoneyWords::from(75))->toBe('خمسة وسبعون دولارا')
        ->and(ArabicMoneyWords::from(7.25))->toBe('سبعة دولارات وخمسة وعشرون سنتا')
        ->and(ArabicMoneyWords::from(2))->toBe('دولاران')
        ->and(ArabicMoneyWords::from(2.50))->toBe('دولاران وخمسون سنتا')
        ->and(ArabicMoneyWords::from(0))->toBe('صفر دولار')
        ->and(ArabicMoneyWords::phrase(100))->toBe('مئة دولار فقط ');
});

test('arabic money words spells iqd amounts with currency name', function () {
    SystemSetting::current()->update(['currency' => Currency::Iqd]);

    expect(ArabicMoneyWords::from(100))->toBe('مئة دينار')
        ->and(ArabicMoneyWords::from(25.5))->toBe('خمسة وعشرون دينارا وخمسمئة فلس')
        ->and(ArabicMoneyWords::from(0))->toBe('صفر دينار')
        ->and(ArabicMoneyWords::phrase(100))->toBe('مئة دينار فقط ');
});
