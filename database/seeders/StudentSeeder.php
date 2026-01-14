<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Student;
use App\Models\StudentProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing customers and student profiles
        $customers = Customer::limit(3)->get();
        $profiles = StudentProfile::limit(15)->get();

        if ($customers->isEmpty()) {
            return; // Can't create students without customers
        }

        $profileArray = $profiles->pluck('id')->toArray();
        if (empty($profileArray)) {
            $profileArray = [1, 2]; // Fallback if no profiles
        }

        // Create students for first customer
        Student::create([
            'name' => 'João Silva',
            'date_of_birth' => '2010-05-15',
            'state_of_birth' => 'SP',
            'city_of_birth' => 'São Paulo',
            'affiliation_1' => 'Maria Silva',
            'affiliation_2' => 'Carlos Silva',
            'phone_primary' => '(11) 98888-1111',
            'phone_secondary' => '(11) 98888-1112',
            'reg_number' => 'REG001',
            'doc_number' => '10987654321',
            'customer_id' => $customers[0]->id,
            'gender' => 'M',
        ]);

        Student::create([
            'name' => 'Ana Silva',
            'date_of_birth' => '2009-08-22',
            'state_of_birth' => 'SP',
            'city_of_birth' => 'São Paulo',
            'affiliation_1' => 'Maria Silva',
            'affiliation_2' => 'Carlos Silva',
            'phone_primary' => '(11) 98888-1111',
            'phone_secondary' => '(11) 98888-1112',
            'reg_number' => 'REG002',
            'doc_number' => '10987654322',
            'customer_id' => $customers[0]->id,
            'gender' => 'F',
        ]);

        // Create students for second customer
        Student::create([
            'name' => 'Pedro Santos',
            'date_of_birth' => '2010-03-10',
            'state_of_birth' => 'SP',
            'city_of_birth' => 'São Paulo',
            'affiliation_1' => 'João Santos',
            'affiliation_2' => 'Lucia Santos',
            'phone_primary' => '(11) 98888-2222',
            'phone_secondary' => '(11) 98888-2223',
            'reg_number' => 'REG003',
            'doc_number' => '10987654323',
            'customer_id' => $customers[1]->id,
            'gender' => 'M',
        ]);

        Student::create([
            'name' => 'Lucas Santos',
            'date_of_birth' => '2011-01-05',
            'state_of_birth' => 'SP',
            'city_of_birth' => 'São Paulo',
            'affiliation_1' => 'João Santos',
            'affiliation_2' => 'Lucia Santos',
            'phone_primary' => '(11) 98888-2222',
            'phone_secondary' => '(11) 98888-2223',
            'reg_number' => 'REG004',
            'doc_number' => '10987654324',
            'customer_id' => $customers[1]->id,
            'gender' => 'M',
        ]);

        // Create students for third customer
        Student::create([
            'name' => 'Fernanda Costa',
            'date_of_birth' => '2010-07-18',
            'state_of_birth' => 'SP',
            'city_of_birth' => 'São Paulo',
            'affiliation_1' => 'Patrícia Costa',
            'affiliation_2' => 'Roberto Costa',
            'phone_primary' => '(11) 98888-3333',
            'phone_secondary' => '(11) 98888-3334',
            'reg_number' => 'REG005',
            'doc_number' => '10987654325',
            'customer_id' => $customers[2]->id,
            'gender' => 'F',
        ]);

        Student::create([
            'name' => 'Gabriel Costa',
            'date_of_birth' => '2009-11-30',
            'state_of_birth' => 'SP',
            'city_of_birth' => 'São Paulo',
            'affiliation_1' => 'Patrícia Costa',
            'affiliation_2' => 'Roberto Costa',
            'phone_primary' => '(11) 98888-3333',
            'phone_secondary' => '(11) 98888-3334',
            'reg_number' => 'REG006',
            'doc_number' => '10987654326',
            'customer_id' => $customers[2]->id,
            'gender' => 'M',
        ]);
    }
}
