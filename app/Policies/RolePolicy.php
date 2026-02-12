<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['roleview','rolecreate','roleupdate','roledelete']);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasAnyPermission(['roleview','rolecreate','roleupdate','roledelete']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('rolecreate');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roleupdate');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roledelete');
    }

    public function restore(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roledelete');
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roledelete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('roledelete');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermissionTo('roledelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermissionTo('roledelete');
    }
}