<?php

namespace App\Actions\Studios;

use App\Enums\Commune;
use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateStudio
{
    /**
     * Create a studio with the default rules and make the user its owner,
     * in one transaction, so a studio never exists without its owner.
     */
    public function handle(User $owner, string $name, Commune $commune): Studio
    {
        return DB::transaction(function () use ($owner, $name, $commune): Studio {
            $studio = Studio::create([
                'name' => $name,
                'commune' => $commune,
                ...Studio::defaultRules(),
            ]);

            $studio->memberships()->create([
                'user_id' => $owner->id,
                'role' => StudioRole::Owner,
            ]);

            return $studio;
        });
    }
}
