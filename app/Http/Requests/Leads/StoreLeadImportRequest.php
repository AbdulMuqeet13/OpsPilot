<?php

namespace App\Http\Requests\Leads;

use App\Services\Leads\LeadImportLimits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeadImportRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'extensions:csv', 'max:'.app(LeadImportLimits::class)->maxKilobytes()],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $limit = app(LeadImportLimits::class)->maxFilesPerUser();

                if ($limit !== null && $this->user()->workspace->leadImports()->where('user_id', $this->user()->id)->count() >= $limit) {
                    $validator->errors()->add('file', "The demo allows {$limit} imports per person. You've reached that limit.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a .csv file.',
            'file.extensions' => 'Upload a .csv file.',
            'file.max' => 'The file is too large. The limit is :max KB.',
        ];
    }
}
