<?php

namespace Database\Factories;

use App\Models\KnowledgeSnippet;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeSnippet>
 */
class KnowledgeSnippetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'title' => 'Returns within 30 days',
            'content' => 'Unused items can be returned within 30 days of delivery for a full refund. Refunds reach the original payment method within 5 to 7 business days.',
            'embedding' => null,
            'embedding_model' => null,
        ];
    }

    /**
     * @param  list<float>  $vector
     */
    public function embedded(array $vector): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding' => $vector,
            'embedding_model' => 'fake-embedding-model',
        ]);
    }
}
