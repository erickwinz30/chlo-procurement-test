<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create departments
        $departments = [
            ['name' => 'IT', 'code' => 'IT'],
            ['name' => 'Finance', 'code' => 'FIN'],
            ['name' => 'HR', 'code' => 'HR'],
            ['name' => 'Operations', 'code' => 'OPS'],
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }

        // Create users
        User::create([
            'name' => 'Employee User',
            'email' => 'employee@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'department_id' => 1,
        ]);

        User::create([
            'name' => 'Purchasing Staff',
            'email' => 'purchasing@test.com',
            'password' => Hash::make('password'),
            'role' => 'purchasing',
            'department_id' => 2,
        ]);

        User::create([
            'name' => 'Manager User',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'department_id' => 2,
        ]);

        User::create([
            'name' => 'Warehouse Staff',
            'email' => 'warehouse@test.com',
            'password' => Hash::make('password'),
            'role' => 'warehouse',
            'department_id' => 4,
        ]);
    }
}
