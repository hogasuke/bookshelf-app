<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Dummy credentials specified by the assignment, for local exercise data only.
        foreach ([
            ['山田太郎', 'yamada@example.com'],
            ['鈴木花子', 'suzuki@example.com'],
            ['田中一郎', 'tanaka@example.com'],
            ['佐藤美咲', 'sato@example.com'],
            ['高橋健太', 'takahashi@example.com'],
        ] as [$name, $email]) {
            User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('password')]);
        }
    }
}
