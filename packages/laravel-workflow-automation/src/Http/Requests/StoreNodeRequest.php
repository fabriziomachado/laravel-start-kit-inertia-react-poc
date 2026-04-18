<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Requests;

use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Illuminate\Foundation\Http\FormRequest;

final class StoreNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $label = $this->input('label');
        if (is_string($label) && $label !== '' && ! $this->filled('name')) {
            $this->merge(['name' => $label]);
        }
    }

    public function rules(): array
    {
        return [
            'node_key' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'position_x' => ['integer'],
            'position_y' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $registry = app(NodeRegistry::class);

            if (! $registry->has($this->input('node_key', ''))) {
                $validator->errors()->add('node_key', 'Unknown node key: '.$this->input('node_key'));
            }
        });
    }
}
