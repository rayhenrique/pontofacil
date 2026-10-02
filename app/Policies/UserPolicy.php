<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function manageTimeEntries(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function manageEmployees(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->role === UserRole::Manager;
    }
}
