<?php

namespace App\Policies;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CashRegisterPolicy
{
    /**
     * Each person closes her own register. The owner (cash.review) also
     * closes one somebody left open, for example after leaving the team.
     * Someone else's answers 404 to the rest.
     */
    public function close(User $user, CashRegister $register): Response
    {
        if ($register->user_id === $user->id || $user->can('cash.review', $register->studio)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }
}
