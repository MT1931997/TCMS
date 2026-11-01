<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('content') && $this->filled('notes')) {
            $this->merge(['content' => $this->input('notes')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Custom validation messages in Arabic.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'نص الملاحظة مطلوب.',
            'content.string' => 'الملاحظة يجب أن تكون نصاً صالحاً.',
            'content.max' => 'الملاحظة يجب ألا تتجاوز 1000 حرف.',
            'notes.string' => 'التعليق يجب أن يكون نصاً صالحاً.',
            'notes.max' => 'التعليق يجب ألا يتجاوز 1000 حرف.',
        ];
    }
}
