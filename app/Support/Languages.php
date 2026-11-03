<?php

namespace App\Support;

final class Languages
{
    /**
     * Languages offered in the translate menu and the language picker (ISO 639-1 code => English name).
     *
     * @return array<string, string>
     */
    public static function common(): array
    {
        return [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'sv' => 'Swedish',
            'tr' => 'Turkish',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
            'ur' => 'Urdu',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
        ];
    }

    public static function nameFor(string $code): ?string
    {
        return self::common()[strtolower($code)] ?? null;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $code, string $name): array => ['code' => $code, 'name' => $name],
            array_keys(self::common()),
            self::common(),
        );
    }
}
