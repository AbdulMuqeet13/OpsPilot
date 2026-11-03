<?php

namespace Database\Seeders;

use App\Actions\Demo\ResetShowcaseWorkspace;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the Oakline Home showcase workspace with its sample tickets, leads and AI history.
     */
    public function run(ResetShowcaseWorkspace $resetShowcase): void
    {
        $workspace = $resetShowcase->handle();

        $this->command?->info("Seeded {$workspace->brand_name}: sign in as ".config('demo.showcase.admin_email').' / '.config('demo.showcase.password'));
    }
}
