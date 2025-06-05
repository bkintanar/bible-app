<?php

namespace App\Http\Requests;

use App\Services\BibleService;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class SwitchTranslationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allow all users to switch translations
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'translation' => [
                'required',
                'string',
                'max:50',
                Rule::exists('bible_versions', 'abbreviation'), // Validate against actual translations in DB
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'translation.required' => 'Translation selection is required.',
            'translation.string' => 'Translation must be a valid string.',
            'translation.max' => 'Translation identifier is too long.',
            'translation.exists' => 'The selected translation is not available.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'translation' => 'translation',
        ];
    }

    /**
     * Configure the validator instance for additional checks.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional check using BibleService (if exists rule doesn't work)
            if ($this->input('translation')) {
                $bibleService = app(BibleService::class);
                if (! $bibleService->translationExists($this->input('translation'))) {
                    $validator->errors()->add(
                        'translation',
                        'The selected translation is not currently available.'
                    );
                }
            }
        });
    }

    /**
     * Get the validated translation key.
     */
    public function getTranslationKey(): string
    {
        return $this->validated()['translation'];
    }
}
