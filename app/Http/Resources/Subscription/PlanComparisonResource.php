<?php

namespace App\Http\Resources\Subscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanComparisonResource extends JsonResource
{
    /**
     * The $resource here is an array (not a model) from SubscriptionService::comparePlans().
     */
    public function toArray(Request $request): array
    {
        return [
            'plans' => SubscriptionPlanResource::collection(collect($this->resource['plans'])),
            'features' => $this->resource['features'],
            'limits' => $this->resource['limits'],
        ];
    }
}
