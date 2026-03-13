<?php

namespace App\Domain\Payment\Requests;

use App\Domain\Payment\Enums\CashEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateCashEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_session_id' => ['required', 'uuid', 'exists:cash_sessions,id'],
            'type'            => ['required', new Enum(CashEventType::class)],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'reason'          => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
