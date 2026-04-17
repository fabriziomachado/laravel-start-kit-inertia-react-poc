<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCredentialRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
