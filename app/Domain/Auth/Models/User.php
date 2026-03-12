<?php

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Enums\UserStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    protected $table = 'users';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Spatie permission guard — matches the guard_name on roles/permissions tables.
     */
    protected string $guard_name = 'sanctum';

    protected $fillable = [
        'store_id',
        'organization_id',
        'name',
        'email',
        'phone',
        'password_hash',
        'pin_hash',
        'role',
        'locale',
        'is_active',
        'email_verified_at',
        'last_login_at',
    ];

    protected $casts = [
        'role' => UserRole::class,
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
        'pin_hash',
    ];

    // ─── Auth Overrides ──────────────────────────────────────────────

    /**
     * Column used by Laravel auth for password verification.
     * Our schema uses `password_hash` instead of `password`.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    /**
     * Convenience: set hashed password.
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_hash'] = bcrypt($value);
    }

    /**
     * Check if the user's account is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if email is verified.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Mark email as verified.
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Update last login timestamp.
     */
    public function touchLastLogin(): void
    {
        $this->forceFill(['last_login_at' => $this->freshTimestamp()])->save();
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Organization::class);
    }

    public function otpVerifications(): HasMany
    {
        return $this->hasMany(OtpVerification::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function pinOverrides(): HasMany
    {
        return $this->hasMany(PinOverride::class, 'requesting_user_id');
    }
    public function pinOverridesViaAuthorizingUser(): HasMany
    {
        return $this->hasMany(PinOverride::class, 'authorizing_user_id');
    }
    public function roleAuditLog(): HasMany
    {
        return $this->hasMany(RoleAuditLog::class);
    }
    public function userPreference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'performed_by');
    }
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'received_by');
    }
    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'adjusted_by');
    }
    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'created_by');
    }
    public function stockTransfersViaApprovedBy(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'approved_by');
    }
    public function stockTransfersViaReceivedBy(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'received_by');
    }
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class, 'performed_by');
    }
    public function storeCreditTransactions(): HasMany
    {
        return $this->hasMany(StoreCreditTransaction::class, 'performed_by');
    }
    public function posSessions(): HasMany
    {
        return $this->hasMany(PosSession::class, 'cashier_id');
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'cashier_id');
    }
    public function heldCarts(): HasMany
    {
        return $this->hasMany(HeldCart::class, 'cashier_id');
    }
    public function heldCartsViaRecalledBy(): HasMany
    {
        return $this->hasMany(HeldCart::class, 'recalled_by');
    }
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }
    public function orderStatusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'changed_by');
    }
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class, 'processed_by');
    }
    public function exchanges(): HasMany
    {
        return $this->hasMany(Exchange::class, 'processed_by');
    }
    public function pendingOrders(): HasMany
    {
        return $this->hasMany(PendingOrder::class, 'created_by');
    }
    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'opened_by');
    }
    public function cashSessionsViaClosedBy(): HasMany
    {
        return $this->hasMany(CashSession::class, 'closed_by');
    }
    public function cashEvents(): HasMany
    {
        return $this->hasMany(CashEvent::class, 'performed_by');
    }
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'recorded_by');
    }
    public function giftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'issued_by');
    }
    public function giftCardTransactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class, 'performed_by');
    }
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'processed_by');
    }
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }
    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }
    public function syncConflicts(): HasMany
    {
        return $this->hasMany(SyncConflict::class, 'resolved_by');
    }
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
    public function labelTemplates(): HasMany
    {
        return $this->hasMany(LabelTemplate::class, 'created_by');
    }
    public function labelPrintHistory(): HasMany
    {
        return $this->hasMany(LabelPrintHistory::class, 'printed_by');
    }
}
