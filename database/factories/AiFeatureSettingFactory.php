<?php

namespace Database\Factories;

use App\Enums\AiFeature;
use App\Models\AiFeatureSetting;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiFeatureSetting>
 */
class AiFeatureSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'feature' => AiFeature::TicketTriage,
            'is_enabled' => true,
            'custom_instructions' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
