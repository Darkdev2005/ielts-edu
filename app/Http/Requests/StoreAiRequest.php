<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'task' => ['required', 'string', 'in:grammar_explain,writing_feedback,generic'],
            'prompt' => ['required', 'string', 'max:4000'],
            'context' => ['nullable', 'array'],
            'parameters' => ['nullable', 'array'],
            'parameters.temperature' => ['nullable', 'numeric', 'between:0,1'],
            'parameters.max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:4096'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $context = $this->input('context', []);
            if ($context && strlen(json_encode($context)) > 10000) {
                $validator->errors()->add('context', 'Context is too large.');
            }
        });
    }
}
