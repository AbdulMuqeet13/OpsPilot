<?php

namespace App\Actions\Settings;

use App\Models\Workspace;
use Illuminate\Support\Str;

class RotateWorkspaceApiKey
{
    public const string PREFIX = 'ops_';

    /**
     * Issue a new key, invalidating the old one. Only a SHA-256 hash is stored; the plain key is returned once.
     */
    public function handle(Workspace $workspace): string
    {
        $plainKey = self::PREFIX.Str::random(40);

        $workspace->forceFill([
            'api_key_hash' => self::hash($plainKey),
            'api_key_last_four' => substr($plainKey, -4),
            'api_key_created_at' => now(),
        ])->save();

        return $plainKey;
    }

    public function revoke(Workspace $workspace): void
    {
        $workspace->forceFill([
            'api_key_hash' => null,
            'api_key_last_four' => null,
            'api_key_created_at' => null,
        ])->save();
    }

    public static function hash(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }
}
