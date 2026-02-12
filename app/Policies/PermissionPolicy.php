<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['permissionview','permissioncreate','permissionupdate','permissiondelete']);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasAnyPermission(['permissionview','permissioncreate','permissionupdate','permissiondelete']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('permissioncreate');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('permissionupdate');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('permissiondelete');
    }

    public function restore(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('permissiondelete');
    }

    public function forceDelete(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('permissiondelete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('permissiondelete');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermissionTo('permissiondelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermissionTo('permissiondelete');
    }
}