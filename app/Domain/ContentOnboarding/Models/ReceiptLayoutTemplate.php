<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\ZatcaCompliance\Enums\ZatcaQrPosition;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceiptLayoutTemplate extends Model
{
    use HasUuids;

    protected $table = 'receipt_layout_templates';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'paper_width',
        'header_config',
        'body_config',
        'footer_config',
        'zatca_qr_position',
        'show_bilingual',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'zatca_qr_position' => ZatcaQrPosition::class,
        'header_config' => 'array',
        'body_config' => 'array',
        'footer_config' => 'array',
        'show_bilingual' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function receiptTemplatePackageVisibility(): HasMany
    {
        return $this->hasMany(ReceiptTemplatePackageVisibility::class);
    }

    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(
            SubscriptionPlan::class,
            'receipt_template_package_visibility',
            'receipt_layout_template_id',
            'subscription_plan_id',
        );
    }
}
