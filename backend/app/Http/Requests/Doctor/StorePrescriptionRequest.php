<?php

namespace App\Http\Requests\Doctor;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\PrescriptionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Write or edit a prescription (FR-J.4, RX-1, RX-5):
 *
 *     { visit_id?, issued_on?, notes?, items: [{ drug_id?, drug_name?, instructions }] }
 *
 * Used by POST /doctor/patients/{patient}/prescriptions and PUT /doctor/prescriptions/{prescription}.
 */
class StorePrescriptionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:'.PrescriptionService::MAX_ITEMS],
            'items.*' => ['array'],
            'items.*.drug_id' => ['nullable', 'integer', 'exists:drugs,id'],
            'items.*.drug_name' => ['nullable', 'required_without:items.*.drug_id', 'string', 'max:150'],
            'items.*.instructions' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'visit_id' => __('visit'),
            'issued_on' => __('prescription date'),
            'notes' => __('notes'),
            'items' => __('prescription lines'),
            'items.*.drug_id' => __('drug'),
            'items.*.drug_name' => __('drug'),
            'items.*.instructions' => __('instructions'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.drug_name.required_without' => __('Choose a drug or write its name.'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('visit_id')) {
                    return;
                }

                // RX-5: the visit must be one of this patient's visits.
                if (Visit::find($this->integer('visit_id'))?->patient_id !== $this->patient()->id) {
                    $validator->errors()->add('visit_id', __('This visit belongs to another patient.'));
                }
            },
        ];
    }

    /**
     * The patient from the route: the URL's patient on create, the prescription's on update.
     */
    public function patient(): Patient
    {
        $prescription = $this->route('prescription');

        return $prescription instanceof Prescription ? $prescription->patient : $this->route('patient');
    }
}
