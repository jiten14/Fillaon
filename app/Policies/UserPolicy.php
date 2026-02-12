<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['userview','usercreate','userupdate','userdelete']);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasAnyPermission(['userview','usercreate','userupdate','userdelete']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('usercreate');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo('userupdate');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('userdelete');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasPermissionTo('userdelete');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('userdelete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('userdelete');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermissionTo('userdelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermissionTo('userdelete');
    }
}