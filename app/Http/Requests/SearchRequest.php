<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allow all users to search
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'q' => 'required|string|min:2|max:255',
            'limit' => 'integer|min:1|max:500',
            'type' => 'string|in:text,strongs',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'q.required' => 'Search query is required.',
            'q.min' => 'Search query must be at least 2 characters long.',
            'q.max' => 'Search query cannot exceed 255 characters.',
            'limit.integer' => 'Limit must be a valid integer.',
            'limit.min' => 'Limit must be at least 1.',
            'limit.max' => 'Limit cannot exceed 500.',
            'type.in' => 'Search type must be either "text" or "strongs".',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'q' => 'search query',
            'limit' => 'results limit',
            'type' => 'search type',
        ];
    }

    /**
     * Configure the validator instance.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Custom validation: if type is 'strongs', query should look like a Strong's number
            if ($this->input('type') === 'strongs' && $this->input('q')) {
                $query = $this->input('q');
                // Strong's numbers typically start with G (Greek) or H (Hebrew) followed by digits
                if (! preg_match('/^[GH]\d+$/i', $query)) {
                    $validator->errors()->add(
                        'q',
                        'Strong\'s number must be in format G#### or H#### (e.g., G2316, H430).'
                    );
                }
            }
        });
    }

    /**
     * Get the validated and processed search parameters.
     */
    public function getSearchParameters(): array
    {
        $validated = $this->validated();

        return [
            'query' => $validated['q'],
            'limit' => $validated['limit'] ?? 100,
            'type' => $validated['type'] ?? 'text',
        ];
    }

    /**
     * Determine if the request expects a JSON response.
     */
    public function expectsJson(): bool
    {
        return true; // Always return JSON for API endpoints
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'timestamp' => now()->toISOString(),
            ], 422)
        );
    }
}
