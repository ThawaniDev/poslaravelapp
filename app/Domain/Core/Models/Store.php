<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Enums\BusinessType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    use HasUuids;

    protected $table = 'stores';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'name',
        'name_ar',
        'slug',
        'branch_code',
        'address',
        'city',
        'latitude',
        'longitude',
        'phone',
        'email',
        'timezone',
        'currency',
        'locale',
        'business_type',
        'is_active',
        'is_main_branch',
        'storage_used_mb',
    ];

    protected $casts = [
        'business_type' => BusinessType::class,
        'is_active' => 'boolean',
        'is_main_branch' => 'boolean',
        'latitude' => 'decimal:2',
        'longitude' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function storeSettings(): HasOne
    {
        return $this->hasOne(StoreSettings::class);
    }
    public function workingHours(): HasMany
    {
        return $this->hasMany(StoreWorkingHour::class);
    }
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }
    public function appUpdateStats(): HasMany
    {
        return $this->hasMany(AppUpdateStat::class);
    }
    public function storeSubscription(): HasOne
    {
        return $this->hasOne(StoreSubscription::class);
    }
    public function storeAddOns(): HasMany
    {
        return $this->hasMany(StoreAddOn::class);
    }
    public function subscriptionUsageSnapshots(): HasMany
    {
        return $this->hasMany(SubscriptionUsageSnapshot::class);
    }
    public function providerBackupStatus(): HasMany
    {
        return $this->hasMany(ProviderBackupStatus::class);
    }
    public function providerLimitOverrides(): HasMany
    {
        return $this->hasMany(ProviderLimitOverride::class);
    }
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
    public function pinOverrides(): HasMany
    {
        return $this->hasMany(PinOverride::class);
    }
    public function roleAuditLog(): HasMany
    {
        return $this->hasMany(RoleAuditLog::class);
    }
    public function staffUsers(): HasMany
    {
        return $this->hasMany(StaffUser::class);
    }
    public function staffBranchAssignments(): HasMany
    {
        return $this->hasMany(StaffBranchAssignment::class, 'branch_id');
    }
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
    public function shiftTemplates(): HasMany
    {
        return $this->hasMany(ShiftTemplate::class);
    }
    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }
    public function commissionRules(): HasMany
    {
        return $this->hasMany(CommissionRule::class);
    }
    public function staffActivityLog(): HasMany
    {
        return $this->hasMany(StaffActivityLog::class);
    }
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }
    public function deviceRegistrations(): HasMany
    {
        return $this->hasMany(DeviceRegistration::class);
    }
    public function securityAuditLog(): HasMany
    {
        return $this->hasMany(SecurityAuditLog::class);
    }
    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }
    public function securityPolicy(): HasOne
    {
        return $this->hasOne(SecurityPolicy::class);
    }
    public function translationOverrides(): HasMany
    {
        return $this->hasMany(TranslationOverride::class);
    }
    public function storePrices(): HasMany
    {
        return $this->hasMany(StorePrice::class);
    }
    public function internalBarcodeSequence(): HasOne
    {
        return $this->hasOne(InternalBarcodeSequence::class);
    }
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }
    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_store_id');
    }
    public function stockTransfersViaToStore(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_store_id');
    }
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }
    public function cfdConfiguration(): HasOne
    {
        return $this->hasOne(CfdConfiguration::class);
    }
    public function signagePlaylists(): HasMany
    {
        return $this->hasMany(SignagePlaylist::class);
    }
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
    public function giftRegistries(): HasMany
    {
        return $this->hasMany(GiftRegistry::class);
    }
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }
    public function loyaltyChallenges(): HasMany
    {
        return $this->hasMany(LoyaltyChallenge::class);
    }
    public function loyaltyBadges(): HasMany
    {
        return $this->hasMany(LoyaltyBadge::class);
    }
    public function loyaltyTiers(): HasMany
    {
        return $this->hasMany(LoyaltyTier::class);
    }
    public function posSessions(): HasMany
    {
        return $this->hasMany(PosSession::class);
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    public function heldCarts(): HasMany
    {
        return $this->hasMany(HeldCart::class);
    }
    public function posCustomizationSetting(): HasOne
    {
        return $this->hasOne(PosCustomizationSetting::class);
    }
    public function receiptTemplate(): HasOne
    {
        return $this->hasOne(ReceiptTemplate::class);
    }
    public function quickAccessConfig(): HasOne
    {
        return $this->hasOne(QuickAccessConfig::class);
    }
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }
    public function exchanges(): HasMany
    {
        return $this->hasMany(Exchange::class);
    }
    public function pendingOrders(): HasMany
    {
        return $this->hasMany(PendingOrder::class);
    }
    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
    public function giftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'issued_at_store');
    }
    public function giftCardTransactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }
    public function storeDeliveryPlatforms(): HasMany
    {
        return $this->hasMany(StoreDeliveryPlatform::class);
    }
    public function deliveryPlatformConfigs(): HasMany
    {
        return $this->hasMany(DeliveryPlatformConfig::class);
    }
    public function deliveryMenuSyncLogs(): HasMany
    {
        return $this->hasMany(DeliveryMenuSyncLog::class);
    }
    public function storeDeliveryPlatformEnrollments(): HasMany
    {
        return $this->hasMany(StoreDeliveryPlatformEnrollment::class);
    }
    public function storeAccountingConfig(): HasOne
    {
        return $this->hasOne(StoreAccountingConfig::class);
    }
    public function accountMappings(): HasMany
    {
        return $this->hasMany(AccountMapping::class);
    }
    public function accountingExports(): HasMany
    {
        return $this->hasMany(AccountingExport::class);
    }
    public function autoExportConfig(): HasOne
    {
        return $this->hasOne(AutoExportConfig::class);
    }
    public function thawaniStoreConfig(): HasOne
    {
        return $this->hasOne(ThawaniStoreConfig::class);
    }
    public function thawaniProductMappings(): HasMany
    {
        return $this->hasMany(ThawaniProductMapping::class);
    }
    public function thawaniOrderMappings(): HasMany
    {
        return $this->hasMany(ThawaniOrderMapping::class);
    }
    public function thawaniSettlements(): HasMany
    {
        return $this->hasMany(ThawaniSettlement::class);
    }
    public function zatcaInvoices(): HasMany
    {
        return $this->hasMany(ZatcaInvoice::class);
    }
    public function zatcaCertificates(): HasMany
    {
        return $this->hasMany(ZatcaCertificate::class);
    }
    public function platformAnnouncementDismissals(): HasMany
    {
        return $this->hasMany(PlatformAnnouncementDismissal::class);
    }
    public function productSalesSummary(): HasMany
    {
        return $this->hasMany(ProductSalesSummary::class);
    }
    public function dailySalesSummary(): HasMany
    {
        return $this->hasMany(DailySalesSummary::class);
    }
    public function storeHealthSnapshots(): HasMany
    {
        return $this->hasMany(StoreHealthSnapshot::class);
    }
    public function hardwareConfigurations(): HasMany
    {
        return $this->hasMany(HardwareConfiguration::class);
    }
    public function hardwareEventLog(): HasMany
    {
        return $this->hasMany(HardwareEventLog::class);
    }
    public function hardwareSales(): HasMany
    {
        return $this->hasMany(HardwareSale::class);
    }
    public function implementationFees(): HasMany
    {
        return $this->hasMany(ImplementationFee::class);
    }
    public function backupHistory(): HasMany
    {
        return $this->hasMany(BackupHistory::class);
    }
    public function syncConflicts(): HasMany
    {
        return $this->hasMany(SyncConflict::class);
    }
    public function syncLog(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
    public function labelPrintHistory(): HasMany
    {
        return $this->hasMany(LabelPrintHistory::class);
    }
    public function onboardingProgress(): HasOne
    {
        return $this->hasOne(OnboardingProgress::class);
    }
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }
    public function dailyMetalRates(): HasMany
    {
        return $this->hasMany(DailyMetalRate::class);
    }
    public function buybackTransactions(): HasMany
    {
        return $this->hasMany(BuybackTransaction::class);
    }
    public function deviceImeiRecords(): HasMany
    {
        return $this->hasMany(DeviceImeiRecord::class);
    }
    public function repairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class);
    }
    public function tradeInRecords(): HasMany
    {
        return $this->hasMany(TradeInRecord::class);
    }
    public function flowerArrangements(): HasMany
    {
        return $this->hasMany(FlowerArrangement::class);
    }
    public function flowerFreshnessLog(): HasMany
    {
        return $this->hasMany(FlowerFreshnessLog::class);
    }
    public function flowerSubscriptions(): HasMany
    {
        return $this->hasMany(FlowerSubscription::class);
    }
    public function bakeryRecipes(): HasMany
    {
        return $this->hasMany(BakeryRecipe::class);
    }
    public function productionSchedules(): HasMany
    {
        return $this->hasMany(ProductionSchedule::class);
    }
    public function customCakeOrders(): HasMany
    {
        return $this->hasMany(CustomCakeOrder::class);
    }
    public function restaurantTables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }
    public function kitchenTickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class);
    }
    public function tableReservations(): HasMany
    {
        return $this->hasMany(TableReservation::class);
    }
    public function openTabs(): HasMany
    {
        return $this->hasMany(OpenTab::class);
    }
}
