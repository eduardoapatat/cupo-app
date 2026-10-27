<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The email with the link to join a studio's team. It goes to an address,
 * not a user, because the person may not have an account yet.
 */
class StudioInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invitation $invitation)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification. The link is signed
     * and expires with the invitation.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $invitation = $this->invitation;
        $studio = $invitation->studio->name;

        return (new MailMessage)
            ->subject(__('You are invited to :studio', ['studio' => $studio]))
            ->line(__(':name invited you to join :studio as :role.', [
                'name' => $invitation->invitedBy->name ?? __('The owner'),
                'studio' => $studio,
                'role' => $invitation->role->label(),
            ]))
            ->action(
                __('See the invitation'),
                URL::temporarySignedRoute('invitations.show', $invitation->expires_at, ['invitation' => $invitation->id]),
            )
            ->line(__('The invitation expires on :date.', [
                'date' => $invitation->expires_at->timezone(config()->string('studio.timezone'))->translatedFormat('j \d\e F'),
            ]))
            ->line(__('If you were not expecting it, you can ignore this email.'));
    }
}
