<?php

namespace App\Console\Commands;

use App\Actions\Classes\GenerateSessions;
use App\Models\Studio;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sessions:generate')]
#[Description('Create the sessions of every studio\'s weekly schedule for the days students can book')]
class GenerateSessionsCommand extends Command
{
    /**
     * Execute the console command. It runs every day, so the last day a
     * student can book always has its sessions.
     */
    public function handle(GenerateSessions $generateSessions): int
    {
        $created = 0;

        Studio::query()->orderBy('id')->each(function (Studio $studio) use ($generateSessions, &$created): void {
            $created += $generateSessions->handle($studio);
        });

        $this->info("Sessions created: {$created}.");

        return self::SUCCESS;
    }
}
