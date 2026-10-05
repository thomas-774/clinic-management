<?php

namespace App\Http\Requests\Patient;

use App\Http\Requests\Concerns\ValidatesPhone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A patient may change only their phone and address (FR-B.5). Any other
 * field in the request (name, current_illness, …) is ignored.
 */
class UpdateOwnProfileRequest extends FormRequest
{
    use ValidatesPhone;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['sometimes', 'required', ...$this->phoneRules($this->user()->id)],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
    }
}
