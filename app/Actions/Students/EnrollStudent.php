<?php

namespace App\Actions\Students;

use App\Enums\StudioRole;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollStudent
{
    public function __construct(private SendActivation $sendActivation) {}

    /**
     * Enroll at the counter a student who does not use the app: a person
     * with a name and a phone, no password, and a student membership. The
     * front desk declares she is 18 or older. With an email, she also gets
     * the link to activate her account.
     */
    public function handle(Studio $studio, string $name, string $phone, ?string $email): Membership
    {
        $membership = DB::transaction(function () use ($studio, $name, $phone, $email): Membership {
            $student = User::create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'adult_declared_at' => now(),
            ]);

            return $studio->memberships()->create([
                'user_id' => $student->id,
                'role' => StudioRole::Student,
            ]);
        });

        $this->sendActivation->handle($membership->user, $studio);

        return $membership;
    }
}
