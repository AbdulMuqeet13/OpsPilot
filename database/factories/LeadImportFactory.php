<?php

namespace Database\Factories;

use App\Enums\LeadImportStatus;
use App\Models\LeadImport;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadImport>
 */
class LeadImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'original_filename' => 'leads.csv',
            'path' => 'lead-imports/leads.csv',
            'status' => LeadImportStatus::Uploaded,
            'headers' => ['Full name', 'Email', 'Company'],
            'preview_rows' => [['Dana Whitfield', 'dana@example.com', 'Northwind Interiors']],
            'column_map' => null,
            'score_leads' => true,
        ];
    }
}
