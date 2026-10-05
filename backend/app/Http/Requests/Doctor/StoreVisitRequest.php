<?php

namespace App\Http\Requests\Doctor;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Services\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Today's visit with its first payment (FR-D.1 – D.4, §6.4). `remaining` is
 * never read from the request; the server computes it (PR-1).
 */
class StoreVisitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'work_done' => ['required', 'string', 'max:5000'],
            'total_amount' => ['required', ...self::moneyRules()],
            'paid_now' => ['nullable', ...self::moneyRules()],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * EGP with at most two decimals, stored in DECIMAL(10,2) (PR-6).
     *
     * @return list<string>
     */
    public static function moneyRules(): array
    {
        return ['numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // PR-2: the first payment cannot be more than the total.
                if (bccomp(PaymentService::money($this->input('paid_now')), PaymentService::money($this->input('total_amount')), 2) > 0) {
                    $validator->errors()->add('paid_now', __('The amount paid cannot be more than the total.'));
                }

                $appointment = $this->filled('appointment_id') ? Appointment::find($this->integer('appointment_id')) : null;
                if (! $appointment) {
                    return;
                }

                if ($appointment->patient_id !== $this->integer('patient_id')) {
                    $validator->errors()->add('appointment_id', __('This appointment belongs to another patient.'));
                } elseif (! in_array($appointment->status, [AppointmentStatus::Booked, AppointmentStatus::CheckedIn], true)) {
                    $validator->errors()->add('appointment_id', __('This appointment is not open for a visit.'));
                } elseif ($appointment->visit()->exists()) {
                    $validator->errors()->add('appointment_id', __('This appointment already has a visit.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'patient_id' => __('patient'),
            'appointment_id' => __('appointment'),
            'work_done' => __('work done'),
            'total_amount' => __('total cost'),
            'paid_now' => __('amount paid now'),
            'method' => __('payment method'),
        ];
    }
}
