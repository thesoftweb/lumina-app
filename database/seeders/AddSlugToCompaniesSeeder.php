<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AddSlugToCompaniesSeeder extends Seeder
{
    public function run(): void
    {
        // Atualiza todas as companies existentes com slugs baseados no nome
        Company::whereNull('slug')->each(function (Company $company) {
            $company->update([
                'slug' => Str::slug($company->name, '-'),
            ]);
        });
    }
}
