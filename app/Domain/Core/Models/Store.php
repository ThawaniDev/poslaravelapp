<?php

namespace App\Domain\Core\Models;

use App\Domain\AccountingIntegration\Models\AccountingExport;
use App\Domain\AccountingIntegration\Models\AccountMapping;
use App\Domain\AccountingIntegration\Models\AutoExportConfig;
use App\Domain\AccountingIntegration\Models\StoreAccountingConfig;
use App\Domain\Analytics\Models\StoreHealthSnapshot;
use App\Domain\Announcement\Models\PlatformAnnouncementDismissal;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Domain\Auth\Models\User;
use App\Domain\BackupSync\Models\BackupHistory;
use App\Domain\BackupSync\Models\ProviderBackupStatus;
use App\Domain\BackupSync\Models\SyncConflict;
use App\Domain\BackupSync\Models\SyncLog;
use App\Domain\Billing\Models\HardwareSale;
use App\Domain\Billing\Models\ImplementationFee;
use App\Domain\Catalog\Models\InternalBarcodeSequence;
use App\Domain\Catalog\Models\StorePrice;
use App\Domain\Core\Enums\BusinessType;
use App\Domain\Customer\Models\Appointment;
use App\Domain\Customer\Models\CfdConfiguration;
use App\Domain\Customer\Models\GiftRegistry;
use App\Domain\Customer\Models\LoyaltyBadge;
use App\Domain\Customer\Models\LoyaltyChallenge;
use App\Domain\Customer\Models\LoyaltyTier;
use App\Domain\Customer\Models\SignagePlaylist;
use App\Domain\Customer\Models\Wishlist;
use App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\StoreDeliveryPlatform;
use App\Domain\DeliveryIntegration\Models\StoreDeliveryPlatformEnrollment;
use App\Domain\Hardware\Models\HardwareConfiguration;
use App\Domain\Hardware\Models\HardwareEventLog;
use App\Domain\IndustryBakery\Models\BakeryRecipe;
use App\Domain\IndustryBakery\Models\CustomCakeOrder;
use App\Domain\IndustryBakery\Models\ProductionSchedule;
use App\Domain\IndustryElectronics\Models\DeviceImeiRecord;
use App\Domain\IndustryElectronics\Models\RepairJob;
use App\Domain\IndustryElectronics\Models\TradeInRecord;
use App\Domain\IndustryFlorist\Models\FlowerArrangement;
use App\Domain\IndustryFlorist\Models\FlowerFreshnessLog;
use App\Domain\IndustryFlorist\Models\FlowerSubscription;
use App\Domain\IndustryJewelry\Models\BuybackTransaction;
use App\Domain\IndustryJewelry\Models\DailyMetalRate;
use App\Domain\IndustryPharmacy\Models\Prescription;
use App\Domain\IndustryRestaurant\Models\KitchenTicket;
use App\Domain\IndustryRestaurant\Models\RestaurantTable;
use App\Domain\IndustryRestaurant\Models\TableReservation;
use App\Domain\Inventory\Models\GoodsReceipt;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\LabelPrinting\Models\LabelPrintHistory;
use App\Domain\Order\Models\Exchange;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\PendingOrder;
use App\Domain\Order\Models\SaleReturn;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\Expense;
use App\Domain\Payment\Models\GiftCard;
use App\Domain\Payment\Models\GiftCardTransaction;
use App\Domain\PosCustomization\Models\PosCustomizationSetting;
use App\Domain\PosCustomization\Models\QuickAccessConfig;
use App\Domain\PosCustomization\Models\ReceiptTemplate;
use App\Domain\PosTerminal\Models\HeldCart;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\ProviderRegistration\Models\OnboardingProgress;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreAddOn;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Models\SubscriptionUsageSnapshot;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\PinOverride;
use App\Domain\Security\Models\RoleAuditLog;
use App\Domain\Security\Models\SecurityAuditLog;
use App\Domain\Security\Models\SecurityPolicy;
use App\Domain\StaffManagement\Models\AttendanceRecord;
use App\Domain\StaffManagement\Models\CommissionRule;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Models\ShiftSchedule;
use App\Domain\StaffManagement\Models\ShiftTemplate;
use App\Domain\StaffManagement\Models\StaffActivityLog;
use App\Domain\StaffManagement\Models\StaffBranchAssignment;
use App\Domain\StaffManagement\Models\StaffUser;
use App\Domain\StaffManagement\Models\TrainingSession;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\SystemConfig\Models\TranslationOverride;
use App\Domain\ThawaniIntegration\Models\ThawaniOrderMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniProductMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniSettlement;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ZatcaCompliance\Models\ZatcaCertificate;
use App\Domain\ZatcaCompliance\Models\ZatcaInvoice;
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
        'manager_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'logo_url',
        'cover_image_url',
        'slug',
        'branch_code',
        'address',
        'city',
        'region',
        'postal_code',
        'country',
        'google_maps_url',
        'latitude',
        'longitude',
        'phone',
        'secondary_phone',
        'email',
        'contact_person',
        'timezone',
        'currency',
        'locale',
        'business_type',
        'is_active',
        'is_main_branch',
        'is_warehouse',
        'accepts_online_orders',
        'accepts_reservations',
        'has_delivery',
        'has_pickup',
        'opening_date',
        'closing_date',
        'max_registers',
        'max_staff',
        'area_sqm',
        'seating_capacity',
        'cr_number',
        'vat_number',
        'municipal_license',
        'license_expiry_date',
        'social_links',
        'extra_metadata',
        'internal_notes',
        'sort_order',
        'storage_used_mb',
    ];

    protected $casts = [
        'business_type' => BusinessType::class,
        'is_active' => 'boolean',
        'is_main_branch' => 'boolean',
        'is_warehouse' => 'boolean',
        'accepts_online_orders' => 'boolean',
        'accepts_reservations' => 'boolean',
        'has_delivery' => 'boolean',
        'has_pickup' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'area_sqm' => 'decimal:2',
        'seating_capacity' => 'integer',
        'max_registers' => 'integer',
        'max_staff' => 'integer',
        'sort_order' => 'integer',
        'storage_used_mb' => 'integer',
        'opening_date' => 'date',
        'closing_date' => 'date',
        'license_expiry_date' => 'date',
        'social_links' => 'array',
        'extra_metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $store): void {
            if (empty($store->slug) && ! empty($store->name)) {
                $base = \Illuminate\Support\Str::slug($store->name) ?: \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $store->slug = $slug;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
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
    public function storeAddOns(): HasMany
    {
        return $this->hasMany(StoreAddOn::class);
    }
    public function providerBackupStatus(): HasMany
    {
        return $this->hasMany(ProviderBackupStatus::class);
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
