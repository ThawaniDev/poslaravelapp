<?php

namespace App\Domain\IndustryRestaurant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateKitchenTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'nullable|string',
            'table_id' => 'nullable|string',
            'ticket_number' => 'required|string|max:50',
            'items_json' => 'required|array',
            'station' => 'nullable|string|max:50',
            'course_number' => 'nullable|integer|min:1',
            'fire_at' => 'nullable|date',
        ];
    }
}
