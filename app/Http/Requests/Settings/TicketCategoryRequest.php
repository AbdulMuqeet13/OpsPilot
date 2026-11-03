<?php

namespace App\Http\Requests\Settings;

use App\Enums\CategoryColor;
use App\Models\TicketCategory;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TicketCategoryRequest extends FormRequest
{
    public const int MAX_CATEGORIES = 12;

    public function authorize(): Response
    {
        $category = $this->route('category');

        return $category instanceof TicketCategory
            ? Gate::inspect('manage', $category)
            : Gate::inspect('manageSettings', $this->user()->workspace);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('ticket_categories', 'name')
                    ->where('workspace_id', $this->user()->workspace_id)
                    ->ignore($category instanceof TicketCategory ? $category->id : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::enum(CategoryColor::class)],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->isMethod('post') && $this->user()->workspace->ticketCategories()->count() >= self::MAX_CATEGORIES) {
                    $validator->errors()->add('name', 'You can have up to '.self::MAX_CATEGORIES.' categories. Remove one first.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'You already have a category with that name.'];
    }
}
