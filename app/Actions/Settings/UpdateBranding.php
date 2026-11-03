<?php

namespace App\Actions\Settings;

use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UpdateBranding
{
    /**
     * @param  array{brand_name: string, primary_color: string, support_email: string|null}  $attributes
     */
    public function handle(Workspace $workspace, array $attributes, ?UploadedFile $logo, bool $removeLogo): Workspace
    {
        $disk = Storage::disk(config('opspilot.logo.disk'));
        $previousLogoPath = $workspace->logo_path;

        if ($logo !== null) {
            $storedPath = $logo->store("workspaces/{$workspace->id}/branding", config('opspilot.logo.disk'));

            if ($storedPath === false) {
                throw new RuntimeException('The logo could not be saved. Please try again.');
            }

            $workspace->logo_path = $storedPath;
        } elseif ($removeLogo) {
            $workspace->logo_path = null;
        }

        $workspace->fill([
            'brand_name' => $attributes['brand_name'],
            'primary_color' => strtolower($attributes['primary_color']),
            'support_email' => $attributes['support_email'],
        ])->save();

        if ($previousLogoPath !== null && $previousLogoPath !== $workspace->logo_path) {
            $disk->delete($previousLogoPath);
        }

        return $workspace;
    }
}
