<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use PHPUnit\Framework\TestCase;

class UserPolicyTest extends TestCase
{
    public function test_user_can_view_own_progress_report(): void
    {
        $policy = new UserPolicy;

        $this->assertTrue($policy->viewProgressReport(
            $this->user(10),
            $this->user(10)
        ));
    }

    public function test_admin_can_view_another_users_progress_report(): void
    {
        $policy = new UserPolicy;

        $this->assertTrue($policy->viewProgressReport(
            $this->user(1, 'admin'),
            $this->user(10)
        ));
    }

    public function test_regular_user_cannot_view_another_users_progress_report(): void
    {
        $policy = new UserPolicy;

        $this->assertFalse($policy->viewProgressReport(
            $this->user(1),
            $this->user(10)
        ));
    }

    public function test_profile_management_is_owner_only(): void
    {
        $policy = new UserPolicy;

        $this->assertTrue($policy->manageProfile($this->user(10), $this->user(10)));
        $this->assertFalse($policy->manageProfile($this->user(1), $this->user(10)));
        $this->assertFalse($policy->manageProfile($this->user(1, 'admin'), $this->user(10)));
    }

    private function user(int $id, string $role = 'user'): User
    {
        $user = new User(['name' => 'Test User', 'email' => "user{$id}@example.test", 'role' => $role]);
        $user->id = $id;

        return $user;
    }
}
