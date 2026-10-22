<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Category $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canAdminister();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->canAdminister();
    }

    /**
     * Threads restrict the deletion of their category, deleted ones included:
     * they have to be moved first.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->canAdminister()
            && ! $category->threads()->withTrashed()->exists();
    }
}
