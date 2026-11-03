<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('ticket'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspaceId = $this->user()->workspace_id;

        return [
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(TicketPriority::class)],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ticket_categories', 'id')->where('workspace_id', $workspaceId)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('workspace_id', $workspaceId)],
        ];
    }

    /**
     * @return array{status?: TicketStatus, priority?: TicketPriority|null, category_id?: int|null, assigned_to?: int|null}
     */
    public function changes(): array
    {
        $changes = [];

        $status = $this->enum('status', TicketStatus::class);
        if ($status !== null) {
            $changes['status'] = $status;
        }

        if ($this->has('priority')) {
            $changes['priority'] = $this->enum('priority', TicketPriority::class);
        }

        if ($this->has('category_id')) {
            $changes['category_id'] = $this->filled('category_id') ? $this->integer('category_id') : null;
        }

        if ($this->has('assigned_to')) {
            $changes['assigned_to'] = $this->filled('assigned_to') ? $this->integer('assigned_to') : null;
        }

        return $changes;
    }
}
