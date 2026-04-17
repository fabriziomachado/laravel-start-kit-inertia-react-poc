<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'run_async' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'folder_id' => ['nullable', 'integer', 'exists:'.config('workflow-automation.tables.folders', 'workflow_folders').',id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:'.config('workflow-automation.tables.tags', 'workflow_tags').',id'],
        ];
    }
}
