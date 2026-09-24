<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
            'role' => $data['role'] ?? User::ROLE_CITOYEN,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(User $user, array $data): User
    {
        $user->name = $data['name'];
        $user->email = mb_strtolower($data['email']);
        $user->role = $data['role'];
        $user->phone = $data['phone'] ?? null;
        $user->is_active = (bool) ($data['is_active'] ?? true);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function verifyCredentials(string $email, string $password): ?User
    {
        $user = User::where('email', mb_strtolower($email))->first();

        if (! $user || ! $user->is_active) {
            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}
