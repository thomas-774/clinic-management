<?php

namespace App\Http\Requests\Doctor;

use App\Enums\DrugCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The doctor adds a drug to the catalogue (FR-J.6). There is no price field (FR-J.1).
 */
class StoreDrugRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trade_name' => ['required', 'string', 'max:150'],
            'form' => ['required', 'string', 'max:60'],
            'pack' => ['nullable', 'string', 'max:60'],
            'category' => ['required', Rule::enum(DrugCategory::class)],
            'active_ingredients' => ['required', 'array', 'min:1', 'max:10'],
            'active_ingredients.*.name' => ['required', 'string', 'max:100'],
            'active_ingredients.*.note' => ['nullable', 'string', 'max:255'],
            'uses' => ['required', 'string', 'max:2000'],
            'warnings' => ['nullable', 'string', 'max:2000'],
            'suggested_dose' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'trade_name' => __('trade name'),
            'form' => __('form'),
            'pack' => __('pack'),
            'category' => __('category'),
            'active_ingredients' => __('active ingredients'),
            'active_ingredients.*.name' => __('ingredient name'),
            'active_ingredients.*.note' => __('ingredient note'),
            'uses' => __('uses'),
            'warnings' => __('warnings'),
            'suggested_dose' => __('suggested dose'),
            'is_active' => __('shown in search'),
        ];
    }

    /**
     * The validated fields, with ingredients stored as a clean list of { name, note }.
     *
     * @return array<string, mixed>
     */
    public function drugData(): array
    {
        $data = $this->validated();

        if (array_key_exists('active_ingredients', $data)) {
            $data['active_ingredients'] = collect($data['active_ingredients'])
                ->map(fn (array $ingredient) => [
                    'name' => trim($ingredient['name']),
                    'note' => filled($ingredient['note'] ?? null) ? trim($ingredient['note']) : null,
                ])
                ->values()
                ->all();
        }

        return $data;
    }
}
