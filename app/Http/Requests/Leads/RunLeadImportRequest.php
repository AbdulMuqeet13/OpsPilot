<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadImportStatus;
use App\Models\LeadImport;
use App\Services\Leads\CsvLeadReader;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class RunLeadImportRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('import'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var LeadImport $import */
        $import = $this->route('import');
        $lastColumn = max(count($import->headers) - 1, 0);

        return [
            'column_map' => ['required', 'array:'.implode(',', array_keys(CsvLeadReader::FIELDS))],
            'column_map.*' => ['nullable', 'integer', 'min:0', "max:{$lastColumn}"],
            // An explicit key replaces the wildcard rules for that key, so repeat the range here.
            'column_map.email' => ['required', 'integer', 'min:0', "max:{$lastColumn}"],
            'score_leads' => ['boolean'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var LeadImport $import */
                $import = $this->route('import');

                if ($import->status !== LeadImportStatus::Uploaded) {
                    $validator->errors()->add('column_map', 'This file has already been imported.');
                }

                $map = (array) $this->input('column_map', []);

                if (($map['name'] ?? null) === null && ($map['first_name'] ?? null) === null) {
                    $validator->errors()->add('column_map.name', 'Choose the column with the lead\'s name (or first name).');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['column_map.email.required' => 'Choose the column with the email address.'];
    }

    /**
     * @return array<string, int|null>
     */
    public function columnMap(): array
    {
        return array_map(
            fn (mixed $index): ?int => $index === null || $index === '' ? null : (int) $index,
            array_replace(array_fill_keys(array_keys(CsvLeadReader::FIELDS), null), (array) $this->validated('column_map')),
        );
    }
}
