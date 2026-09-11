<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

/**
 * Организации строго привязаны к аккаунту: чужую карточку нельзя ни
 * посмотреть, ни обновить, ни удалить.
 */
class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->getKey() === $organization->user_id;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization);
    }
}
