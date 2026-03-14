<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    use HasUuids;

    protected $table = 'cms_pages';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'title',
        'title_ar',
        'body',
        'body_ar',
        'page_type',
        'is_published',
        'meta_title',
        'meta_title_ar',
        'meta_description',
        'meta_description_ar',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];
}
