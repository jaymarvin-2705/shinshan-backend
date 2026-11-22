<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function updatePassword(User $user, string $hashedPassword): User
    {
        $user->password = $hashedPassword;
        $user->save();

        return $user;
    }
}