<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@azatech.id',
            'password' => 'password', // otomatis ke-hash via cast 'hashed' di Model User
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Cashier',
            'email' => 'cashier@azatech.id',
            'password' => 'password',
            'role' => 'cashier',
        ]);
    }
}