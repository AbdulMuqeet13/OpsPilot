<?php

namespace App\Services\Leads;

use Generator;
use Illuminate\Support\Str;
use SplFileObject;

/**
 * Reads lead CSVs exported from spreadsheets and CRMs: detects comma vs
 * semicolon delimiters, strips a UTF-8 BOM, skips blank lines, and guesses
 * which column holds which lead field from the header names.
 */
class CsvLeadReader
{
    /**
     * Lead fields an import can fill, with the header names that usually hold them.
     */
    public const array FIELDS = [
        'name' => ['name', 'full name', 'contact', 'contact name', 'lead name'],
        'first_name' => ['first name', 'firstname', 'given name'],
        'last_name' => ['last name', 'lastname', 'surname', 'family name'],
        'email' => ['email', 'e-mail', 'email address', 'work email'],
        'company' => ['company', 'company name', 'organisation', 'organization', 'account'],
        'job_title' => ['title', 'job title', 'role', 'position'],
        'company_size' => ['company size', 'size', 'employees', 'headcount', 'no. of employees'],
        'budget' => ['budget', 'estimated budget', 'deal size'],
        'website' => ['website', 'url', 'company website', 'domain'],
        'message' => ['message', 'notes', 'note', 'comments', 'description', 'enquiry', 'inquiry'],
    ];

    /**
     * @return array{headers: list<string>, preview: list<list<string>>, total: int}
     */
    public function inspect(string $path, int $previewRows = 5): array
    {
        $headers = [];
        $preview = [];
        $total = 0;

        foreach ($this->rows($path) as $index => $row) {
            if ($index === 0) {
                $headers = array_map(fn (string $header): string => Str::limit(trim($header), 60, ''), $row);

                continue;
            }

            $total++;

            if (count($preview) < $previewRows) {
                $preview[] = array_map(fn (string $cell): string => Str::limit($cell, 80, '…'), $row);
            }
        }

        return ['headers' => $headers, 'preview' => $preview, 'total' => $total];
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int|null> Lead field => column index.
     */
    public function guessColumnMap(array $headers): array
    {
        $normalised = array_map($this->normaliseHeader(...), $headers);
        $map = [];

        foreach (self::FIELDS as $field => $aliases) {
            $aliases = array_map($this->normaliseHeader(...), $aliases);
            $index = array_search(true, array_map(fn (string $header): bool => in_array($header, $aliases, true), $normalised), true);
            $map[$field] = $index === false ? null : (int) $index;
        }

        return $map;
    }

    /**
     * "E-mail", "e_mail" and "E mail" should all match the same alias.
     */
    private function normaliseHeader(string $header): string
    {
        return Str::of($header)->lower()->replaceMatches('/[_\-]+/', ' ')->squish()->toString();
    }

    /**
     * Data rows (after the header), each as a list of cell strings.
     *
     * @return Generator<int, list<string>> 1-based data row number => cells
     */
    public function dataRows(string $path): Generator
    {
        foreach ($this->rows($path) as $index => $row) {
            if ($index > 0) {
                yield $index => $row;
            }
        }
    }

    /**
     * @return Generator<int, list<string>>
     */
    private function rows(string $path): Generator
    {
        $file = new SplFileObject($path, 'r');
        $firstLine = (string) $file->fgets();
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $file->rewind();
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter, '"', '');

        $index = 0;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null] || $row === []) {
                continue;
            }

            $cells = array_map(fn (mixed $cell): string => trim((string) $cell), $row);

            if ($index === 0 && isset($cells[0])) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]) ?? $cells[0];
            }

            if (implode('', $cells) === '') {
                continue;
            }

            yield $index++ => array_values($cells);
        }
    }
}
