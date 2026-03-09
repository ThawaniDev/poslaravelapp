<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessType extends Model
{
    use HasUuids;

    protected $table = 'business_types';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function posLayoutTemplates(): HasMany
    {
        return $this->hasMany(PosLayoutTemplate::class);
    }
    public function signageTemplateBusinessTypes(): HasMany
    {
        return $this->hasMany(SignageTemplateBusinessType::class);
    }
    public function labelTemplateBusinessTypes(): HasMany
    {
        return $this->hasMany(LabelTemplateBusinessType::class);
    }
    public function businessTypeCategoryTemplates(): HasMany
    {
        return $this->hasMany(BusinessTypeCategoryTemplate::class);
    }
    public function businessTypeShiftTemplates(): HasMany
    {
        return $this->hasMany(BusinessTypeShiftTemplate::class);
    }
    public function businessTypeReceiptTemplate(): HasOne
    {
        return $this->hasOne(BusinessTypeReceiptTemplate::class);
    }
    public function businessTypeIndustryConfig(): HasOne
    {
        return $this->hasOne(BusinessTypeIndustryConfig::class);
    }
    public function businessTypePromotionTemplates(): HasMany
    {
        return $this->hasMany(BusinessTypePromotionTemplate::class);
    }
    public function businessTypeCommissionTemplates(): HasMany
    {
        return $this->hasMany(BusinessTypeCommissionTemplate::class);
    }
    public function businessTypeLoyaltyConfig(): HasOne
    {
        return $this->hasOne(BusinessTypeLoyaltyConfig::class);
    }
    public function businessTypeCustomerGroupTemplates(): HasMany
    {
        return $this->hasMany(BusinessTypeCustomerGroupTemplate::class);
    }
    public function businessTypeReturnPolicy(): HasOne
    {
        return $this->hasOne(BusinessTypeReturnPolicy::class);
    }
    public function businessTypeWasteReasonTemplates(): HasMany
    {
        return $this->hasMany(BusinessTypeWasteReasonTemplate::class);
    }
    public function businessTypeAppointmentConfig(): HasOne
    {
        return $this->hasOne(BusinessTypeAppointmentConfig::class);
    }
    public function businessTypeServiceCategoryTemplates(): HasMany
    {
        return $this->hasMany(BusinessTypeServiceCategoryTemplate::class);
    }
    public function businessTypeGiftRegistryTypes(): HasMany
    {
        return $this->hasMany(BusinessTypeGiftRegistryType::class);
    }
    public function businessTypeGamificationBadges(): HasMany
    {
        return $this->hasMany(BusinessTypeGamificationBadge::class);
    }
    public function businessTypeGamificationChallenges(): HasMany
    {
        return $this->hasMany(BusinessTypeGamificationChallenge::class);
    }
    public function businessTypeGamificationMilestones(): HasMany
    {
        return $this->hasMany(BusinessTypeGamificationMilestone::class);
    }
    public function providerRegistrations(): HasMany
    {
        return $this->hasMany(ProviderRegistration::class);
    }
}
