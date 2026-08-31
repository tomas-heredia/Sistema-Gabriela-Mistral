<?php

namespace App\Cobranzas\Policies;

use App\Cobranzas\Models\Arancel;
use App\Core\Models\User;

class ArancelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, Arancel $arancel): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Arancel $arancel): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, Arancel $arancel): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }
}
