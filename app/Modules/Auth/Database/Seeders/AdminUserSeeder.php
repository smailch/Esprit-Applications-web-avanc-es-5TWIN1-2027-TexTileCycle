<?php

namespace App\Modules\Auth\Database\Seeders;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(UserService $users): void
    {
        $email = mb_strtolower(env('ADMIN_EMAIL', 'admin@textilecycle.tn'));

        if (User::where('email', $email)->exists()) {
            return;
        }

        $users->create([
            'name' => env('ADMIN_NAME', 'Administrateur TexTileCycle'),
            'email' => $email,
            'password' => env('ADMIN_PASSWORD', 'password123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }
}
