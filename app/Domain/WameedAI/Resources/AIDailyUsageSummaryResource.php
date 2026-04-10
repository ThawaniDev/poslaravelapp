<?php

namespace App\Domain\WameedAI\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIDailyUsageSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'organization_id'         => $this->organization_id,
            'store_id'                => $this->store_id,
            'date'                    => $this->date,
            'total_requests'          => (int) $this->total_requests,
            'cached_requests'         => (int) $this->cached_requests,
            'failed_requests'         => (int) $this->failed_requests,
            'total_input_tokens'      => (int) $this->total_input_tokens,
            'total_output_tokens'     => (int) $this->total_output_tokens,
            'total_estimated_cost_usd' => (float) $this->total_estimated_cost_usd,
            'feature_breakdown_json'  => $this->feature_breakdown_json,
            'created_at'              => $this->created_at,
        ];
    }
}
