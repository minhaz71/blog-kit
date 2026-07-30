<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared policy behaviour: every ability maps to one (or more) of the
 * flat "manage x" permissions. Super Admin is short-circuited by the
 * Gate::before hook registered in AdminPanelProvider.
 */
trait ChecksPermission
{
    /** @return list<string> */
    abstract protected function permissions(): array;

    protected function allows(User $user): bool
    {
        foreach ($this->permissions() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return $this->allows($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user);
    }

    public function update(User $user, ?Model $model = null): bool
    {
        return $this->allows($user);
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $this->allows($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function restore(User $user, ?Model $model = null): bool
    {
        return $this->allows($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function forceDelete(User $user, ?Model $model = null): bool
    {
        return $this->allows($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function reorder(User $user): bool
    {
        return $this->allows($user);
    }
}
