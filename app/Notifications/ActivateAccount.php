<?php

namespace App\Notifications;

use App\Models\Studio;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The email that lets a student enrolled at the counter start using the app:
 * a signed link to create her password.
 */
class ActivateAccount extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Studio $studio)
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
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Activate your Cupo account'))
            ->line(__(':studio signed you up in Cupo. Create your password to book your classes from your phone.', ['studio' => $this->studio->name]))
            ->action(__('Create my password'), self::url($notifiable))
            ->line(__('The link works for :days days. If it expires, ask the studio to send you a new one.', ['days' => config()->integer('studio.activation_days')]))
            ->line(__('If you were not expecting it, you can ignore this email.'));
    }

    /**
     * The signed activation link. It carries a hash of the email it was sent
     * to, so a link sent to an old address stops working if the front desk
     * corrects the email.
     */
    public static function url(User $user): string
    {
        return URL::temporarySignedRoute(
            'activation.show',
            now()->addDays(config()->integer('studio.activation_days')),
            ['user' => $user->id, 'hash' => sha1((string) $user->email)],
        );
    }
}
