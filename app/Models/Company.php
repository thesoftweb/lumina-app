<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Company extends Model
{
    public const LOGO_DISK = 'public';

    public const LOGO_DIRECTORY = 'company-logos';

    protected $casts = [
        'asaas_key' => 'encrypted',
        'minimum_passing_grade' => 'decimal:2',
        'late_fine_percent' => 'decimal:2',
        'late_interest_monthly_percent' => 'decimal:2',
        'minimum_attendance_percent' => 'decimal:2',
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
                $model->slug = Str::slug($model->name, '-');
            }
        });

        // Ao trocar ou remover a logo, o arquivo anterior sai do disco.
        static::updated(function (Company $company): void {
            $previous = $company->getOriginal('logo_path');

            if ($company->wasChanged('logo_path') && $previous) {
                Storage::disk(self::LOGO_DISK)->delete($previous);
            }
        });

        static::deleted(function (Company $company): void {
            if ($company->logo_path) {
                Storage::disk(self::LOGO_DISK)->delete($company->logo_path);
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

    /** Empresa do usuário; sem vínculo, a única cadastrada (instalação de uma escola só). */
    public static function current(?User $user = null): ?self
    {
        return $user?->company ?? (static::count() === 1 ? static::first() : null);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /** "Santa Rita/PB": usado no cabeçalho e no local e data dos documentos. */
    public function getCityAndStateAttribute(): ?string
    {
        return collect([$this->city?->name, $this->city?->state?->abbreviation])->filter()->implode('/') ?: null;
    }

    public function getFullAddressAttribute(): ?string
    {
        return collect([
            collect([$this->address, $this->address_number])->filter()->implode(', '),
            $this->neighborhood,
            $this->city_and_state,
            $this->postal_code ? "CEP {$this->postal_code}" : null,
        ])->filter()->implode(' - ') ?: null;
    }

    /**
     * Logo embutida como data URI: as páginas de impressão não dependem do APP_URL nem do link
     * public/storage, e o PDF salvo pelo navegador leva a imagem junto.
     */
    public function logoDataUri(): ?string
    {
        $disk = Storage::disk(self::LOGO_DISK);

        if (! $this->logo_path || ! $disk->exists($this->logo_path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($this->logo_path).';base64,'.base64_encode($disk->get($this->logo_path));
    }
}
