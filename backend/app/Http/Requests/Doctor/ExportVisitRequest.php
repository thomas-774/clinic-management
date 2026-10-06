<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The visit report's file format (FR-K.1): pdf or docx.
 */
class ExportVisitRequest extends FormRequest
{
    public const FORMATS = ['pdf', 'docx'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:'.implode(',', self::FORMATS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'format' => __('file format'),
        ];
    }
}
