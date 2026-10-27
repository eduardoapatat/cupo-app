<?php

namespace App\Actions\Students;

use App\Models\Studio;
use App\Models\User;
use App\Notifications\ActivateAccount;

class SendActivation
{
    /**
     * Email a student who does not use the app yet the link to create her
     * password. Nothing happens if she has no email or already uses the app.
     */
    public function handle(User $student, Studio $studio): bool
    {
        if ($student->email === null || $student->usesApp()) {
            return false;
        }

        $student->notify(new ActivateAccount($studio));

        return true;
    }
}
