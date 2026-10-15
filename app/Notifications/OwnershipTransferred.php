<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OwnershipTransferred extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Team $team) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('team.transferred'))
            ->greeting(__('team.hello') . ' ' . $notifiable->name . ',')
            ->line(__('team.you_are_new_owner') . $this->team->name . '.')
            ->action(__('team.view_team'), url('/teams/' . $this->team->id))
            ->line(__('team.thank_you'));
    }
}
