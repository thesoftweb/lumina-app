<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test customers (responsible parties for students)
        Customer::create([
            'name' => 'Maria Silva',
            'email' => 'maria.silva@example.com',
            'phone' => '(11) 98888-1111',
            'document' => '12345678901',
        ]);

        Customer::create([
            'name' => 'João Santos',
            'email' => 'joao.santos@example.com',
            'phone' => '(11) 98888-2222',
            'document' => '12345678902',
        ]);

        Customer::create([
            'name' => 'Ana Costa',
            'email' => 'ana.costa@example.com',
            'phone' => '(11) 98888-3333',
            'document' => '12345678903',
        ]);
    }
}
