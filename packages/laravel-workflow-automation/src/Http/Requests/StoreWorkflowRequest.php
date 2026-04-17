<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'run_async' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'folder_id' => ['nullable', 'integer', 'exists:'.config('workflow-automation.tables.folders', 'workflow_folders').',id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:'.config('workflow-automation.tables.tags', 'workflow_tags').',id'],
            'created_via' => ['nullable', 'string', 'in:editor,import,code,api,duplicate'],
        ];
    }
}
