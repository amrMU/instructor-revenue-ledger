<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\User;

class UserRepository
{
    public function findInstructor(int $userId): User
    {
        return User::query()->where('role', UserRole::Instructor)->findOrFail($userId);
    }

    /** @param list<int> $userIds */
    public function countInstructors(array $userIds): int
    {
        return User::query()->whereKey($userIds)->where('role', UserRole::Instructor)->count();
    }
}
