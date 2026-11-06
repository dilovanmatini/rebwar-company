<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PUR-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'receipt_number' => fake()->unique()->numerify('######'),
            'purchase_date' => fake()->date(),
            'supplier_id' => Supplier::factory(),
            'notes' => fake()->optional()->sentence(),
            'status' => DocumentStatus::Draft,
        ];
    }

    public function posted(?User $poster = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Posted,
            'posted_at' => now(),
            'posted_by' => $poster?->id ?? User::factory(),
        ]);
    }

    public function cancelled(?User $poster = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Cancelled,
            'posted_at' => now(),
            'posted_by' => $poster?->id ?? User::factory(),
        ]);
    }

    public function withLines(int $count = 1): static
    {
        return $this->afterCreating(function (Purchase $purchase) use ($count): void {
            PurchaseLine::factory()
                ->count($count)
                ->create(['purchase_id' => $purchase->id]);
        });
    }
}
