<?php

namespace App\Services\AI\Data;

/**
 * A JSON Schema the model's reply must follow. Every object must set
 * "additionalProperties": false and list all of its properties as required.
 */
final readonly class JsonSchema
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        public string $name,
        public array $schema,
    ) {}
}
