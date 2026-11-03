<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'brand_name' => $name,
            'primary_color' => '#4f46e5',
            'support_email' => fake()->companyEmail(),
            'is_sandbox' => false,
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the workspace is a temporary demo sandbox.
     */
    public function sandbox(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sandbox' => true,
            'expires_at' => now()->addDay(),
        ]);
    }
}
