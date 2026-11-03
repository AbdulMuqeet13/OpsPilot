<?php

namespace App\Data;

use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

final readonly class Brand
{
    public const string FALLBACK_PRIMARY_COLOR = '#4f46e5';

    public function __construct(
        public string $name,
        public string $primaryColor,
        public ?string $logoUrl,
        public ?string $supportEmail,
    ) {}

    public static function fromWorkspace(?Workspace $workspace): self
    {
        if ($workspace === null) {
            return self::default();
        }

        return new self(
            name: $workspace->brand_name,
            primaryColor: self::validColorOrFallback($workspace->primary_color),
            logoUrl: $workspace->logo_path
                ? Storage::disk(config('opspilot.logo.disk'))->url($workspace->logo_path)
                : null,
            supportEmail: $workspace->support_email,
        );
    }

    public static function default(): self
    {
        return new self(
            name: config('opspilot.default_brand.name'),
            primaryColor: self::validColorOrFallback(config('opspilot.default_brand.primary_color')),
            logoUrl: null,
            supportEmail: config('opspilot.default_brand.support_email'),
        );
    }

    /**
     * Guards against a misconfigured color, such as an unquoted "#..." in .env being read as a comment.
     */
    public static function validColorOrFallback(mixed $color): string
    {
        return is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color) === 1
            ? strtolower($color)
            : self::FALLBACK_PRIMARY_COLOR;
    }

    /**
     * Text color for content placed on the primary color: white unless the color is light (WCAG relative luminance).
     */
    public function primaryForegroundColor(): string
    {
        [$red, $green, $blue] = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($this->primaryColor, '#'), 2),
        );

        $luminance = 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;

        return $luminance > 0.4 ? '#0f172a' : '#ffffff';
    }

    /**
     * @return array{name: string, primaryColor: string, primaryForegroundColor: string, logoUrl: string|null, supportEmail: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'primaryColor' => $this->primaryColor,
            'primaryForegroundColor' => $this->primaryForegroundColor(),
            'logoUrl' => $this->logoUrl,
            'supportEmail' => $this->supportEmail,
        ];
    }
}
