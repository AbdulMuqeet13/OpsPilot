<?php

namespace Database\Factories;

use App\Enums\CategoryColor;
use App\Models\TicketCategory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketCategory>
 */
class TicketCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->word(),
            'description' => fake()->sentence(),
            'color' => fake()->randomElement(CategoryColor::cases()),
            'position' => 0,
        ];
    }
}
