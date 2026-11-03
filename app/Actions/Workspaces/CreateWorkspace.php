<?php

namespace App\Actions\Workspaces;

use App\Data\Brand;
use App\Models\Workspace;
use Illuminate\Support\Str;

class CreateWorkspace
{
    /**
     * Create a workspace that starts with the default brand settings.
     *
     * @param  array{is_sandbox?: bool, expires_at?: \DateTimeInterface|null, primary_color?: string, support_email?: string|null}  $attributes
     */
    public function handle(string $name, array $attributes = []): Workspace
    {
        return Workspace::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'brand_name' => $name,
            'primary_color' => Brand::validColorOrFallback(config('opspilot.default_brand.primary_color')),
            'support_email' => null,
            ...$attributes,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Workspace::where('slug', $slug)->exists());

        return $slug;
    }
}
