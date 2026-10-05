<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a visit's work and total (FR-D.2, FR-D.3). That the total stays at or
 * above what was already paid (PR-2) is checked under a lock by the controller.
 */
class UpdateVisitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_done' => ['required', 'string', 'max:5000'],
            'total_amount' => ['required', ...StoreVisitRequest::moneyRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'work_done' => __('work done'),
            'total_amount' => __('total cost'),
        ];
    }
}
