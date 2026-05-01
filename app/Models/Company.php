<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $casts = [
        'asaas_key' => 'encrypted',
    ];

    protected $guarded = [];

    /**
     * Obter empresa por slug
     */
    public static function bySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Acessor: slug gerado automaticamente a partir do nome
     * Útil para gerar slug automaticamente antes de salvar
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->slug) {
                $model->slug = \Illuminate\Support\Str::slug($model->name, '-');
            }
        });
    }
}

