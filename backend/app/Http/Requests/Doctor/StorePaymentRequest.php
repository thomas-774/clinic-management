<?php

namespace App\Http\Requests\Doctor;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An installment against a visit (FR-D.6). "At most the remaining amount"
 * (PR-2) is checked by PaymentService with the visit row locked.
 */
class StorePaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', ...StoreVisitRequest::moneyRules(), 'gt:0'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            // When the money was received; counts in that period's revenue (PR-4).
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => __('amount'),
            'method' => __('payment method'),
            'paid_at' => __('payment date'),
        ];
    }
}
