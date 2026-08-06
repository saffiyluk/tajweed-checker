<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * A progress report belongs to its user, while administrators may review
     * reports as part of system oversight.
     */
    public function viewProgressReport(User $actor, User $subject): bool
    {
        return $actor->id === $subject->id || $actor->is_admin;
    }

    /**
     * Profile routes are self-service. Administrator user management has its
     * own protected routes and must not reuse another user's profile URL.
     */
    public function manageProfile(User $actor, User $subject): bool
    {
        return $actor->id === $subject->id;
    }
}
