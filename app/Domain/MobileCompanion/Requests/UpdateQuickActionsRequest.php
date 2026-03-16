<?php

namespace App\Domain\MobileCompanion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuickActionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.id' => ['required', 'string', 'max:50'],
            'actions.*.label' => ['required', 'string', 'max:100'],
            'actions.*.icon' => ['required', 'string', 'max:50'],
            'actions.*.enabled' => ['required', 'boolean'],
            'actions.*.order' => ['required', 'integer', 'min:1'],
        ];
    }
}
