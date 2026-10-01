<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRole;

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
