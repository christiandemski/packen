<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function view(User $user, Template $template): bool
    {
        return $template->isAccessibleBy($user);
    }

    public function update(User $user, Template $template): bool
    {
        return $template->isAccessibleBy($user);
    }

    public function delete(User $user, Template $template): bool
    {
        return $template->isAccessibleBy($user);
    }
}
