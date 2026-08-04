<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $casts = [
        'asaas_key' => 'encrypted',
        'minimum_passing_grade' => 'decimal:2',
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
            if (! $model->slug) {
                $model->slug = \Illuminate\Support\Str::slug($model->name, '-');
            }
        });

        static::created(function (Company $company): void {
            $company->conceptScales()->createMany([
                ['code' => 'A', 'label' => 'Excelente', 'sort_order' => 1, 'color' => 'success', 'is_passing' => true],
                ['code' => 'B', 'label' => 'Bom', 'sort_order' => 2, 'color' => 'info', 'is_passing' => true],
                ['code' => 'C', 'label' => 'Regular', 'sort_order' => 3, 'color' => 'warning', 'is_passing' => true],
                ['code' => 'D', 'label' => 'Insuficiente', 'sort_order' => 4, 'color' => 'danger', 'is_passing' => false],
            ]);
        });
    }

    public function conceptScales()
    {
        return $this->hasMany(ConceptScale::class)->orderBy('sort_order');
    }
}
