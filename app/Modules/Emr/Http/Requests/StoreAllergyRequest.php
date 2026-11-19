<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('emr.note.create') || $user->can('emr.vitals.record'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'substance' => ['required', 'string', 'min:2', 'max:150'],
            'category' => ['required', Rule::in(['medication', 'food', 'contrast_dye', 'environment', 'latex'])],
            'severity' => ['required', Rule::in(['mild', 'moderate', 'severe', 'life_threatening'])],
            'reaction' => ['nullable', 'string', 'max:255'],
        ];
    }
}
