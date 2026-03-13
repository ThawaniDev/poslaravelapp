<?php

namespace App\Domain\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'store_id'          => $this->store_id,
            'cash_session_id'   => $this->cash_session_id,
            'amount'            => (float) $this->amount,
            'category'          => $this->category?->value ?? $this->category,
            'description'       => $this->description,
            'receipt_image_url' => $this->receipt_image_url,
            'recorded_by'       => $this->recorded_by,
            'expense_date'      => $this->expense_date?->toDateString(),
        ];
    }
}
