<?php

namespace App\Domain\ProviderRegistration\Controllers\Api;

use App\Domain\ProviderRegistration\Models\ProviderRegistration;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\ProviderRegistration\StorePublicProviderRegistrationRequest;
use Illuminate\Http\JsonResponse;

class ProviderRegistrationPublicController extends BaseApiController
{
    /**
     * Handle a public (unauthenticated) provider registration submission.
     *
     * POST /api/v2/website/provider-registration
     */
    public function store(StorePublicProviderRegistrationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Prevent duplicate pending / approved registrations for the same email.
        $duplicate = ProviderRegistration::where('owner_email', $validated['owner_email'])
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($duplicate) {
            return $this->error(
                'A registration with this email already exists. Please contact support if you need assistance.',
                409,
            );
        }

        $registration = ProviderRegistration::create([
            'organization_name'    => $validated['organization_name'],
            'organization_name_ar' => $validated['organization_name_ar'] ?? null,
            'owner_name'           => $validated['owner_name'],
            'owner_email'          => $validated['owner_email'],
            'owner_phone'          => $validated['owner_phone'],
            'cr_number'            => $validated['cr_number'] ?? null,
            'vat_number'           => $validated['vat_number'] ?? null,
            'status'               => 'pending',
        ]);

        return $this->created([
            'reference_number' => $registration->id,
        ], 'Your registration has been submitted successfully. Our team will review it and contact you within 2 business days.');
    }
}
