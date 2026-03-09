<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\SystemConfig\Enums\KnowledgeBaseCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseArticle extends Model
{
    use HasUuids;

    protected $table = 'knowledge_base_articles';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'title_ar',
        'slug',
        'body',
        'body_ar',
        'category',
        'delivery_platform_id',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'category' => KnowledgeBaseCategory::class,
        'is_published' => 'boolean',
    ];

}
