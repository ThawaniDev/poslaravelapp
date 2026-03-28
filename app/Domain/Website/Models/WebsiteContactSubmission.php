<?php

namespace App\Domain\Website\Models;

use App\Domain\Website\Enums\ContactSubmissionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebsiteContactSubmission extends Model
{
    use HasUuids;

    protected $table = 'website_contact_submissions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'reference_number',
        'full_name',
        'store_name',
        'phone',
        'email',
        'store_type',
        'branches',
        'message',
        'source_page',
        'selected_plan',
        'inquiry_type',
        'status',
        'admin_notes',
        'assigned_to',
        'contacted_at',
        'converted_at',
        'ip_address',
        'user_agent',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected $casts = [
        'status' => ContactSubmissionStatus::class,
        'contacted_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    // ─── Boot ────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (empty($record->reference_number)) {
                $record->reference_number = 'LEAD-' . strtoupper(Str::random(8));
            }
        });
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', ContactSubmissionStatus::New);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ContactSubmissionStatus::Converted,
            ContactSubmissionStatus::Closed,
        ]);
    }

    public function scopeFromPage(Builder $query, string $page): Builder
    {
        return $query->where('source_page', $page);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('inquiry_type', $type);
    }
}
