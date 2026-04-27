<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $daysUntilExpiry = $this->expiry_date
            ? (int) now()->startOfDay()->diffInDays($this->expiry_date, false)
            : null;

        return [
            'id'              => $this->id,
            'staff_user_id'   => $this->staff_user_id,
            'document_type'   => $this->document_type?->value,
            'file_url'        => $this->file_url,
            'expiry_date'     => $this->expiry_date?->toDateString(),
            'days_until_expiry' => $daysUntilExpiry,
            'is_expired'      => $daysUntilExpiry !== null && $daysUntilExpiry < 0,
            'expiring_soon'   => $daysUntilExpiry !== null && $daysUntilExpiry >= 0 && $daysUntilExpiry <= 30,
            'uploaded_at'     => $this->uploaded_at?->toIso8601String(),
        ];
    }
}
