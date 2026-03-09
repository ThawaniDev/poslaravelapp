<?php

namespace App\Domain\StaffManagement\Models;

use App\Domain\StaffManagement\Enums\StaffDocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDocument extends Model
{
    use HasUuids;

    protected $table = 'staff_documents';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'staff_user_id',
        'document_type',
        'file_url',
        'expiry_date',
        'uploaded_at',
    ];

    protected $casts = [
        'document_type' => StaffDocumentType::class,
        'expiry_date' => 'date',
        'uploaded_at' => 'datetime',
    ];

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
