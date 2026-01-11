<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'content',
        'merge_tags',
    ];

    protected $casts = [
        'merge_tags' => 'json',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
