<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Password;

class UserCreatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected User $user
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Create a password reset token
        $token = Password::broker()->createToken($this->user);
    
        // Build the reset URL (same route name you defined: password.reset -> reset-password/{token})
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $this->user->email,
        ], false));
    
        return (new MailMessage)
            ->subject('Welcome to Audio Annotation Platform')
            ->greeting('Hello ' . $this->user->first_name . '!')
            ->line('An account has been created for you on the Audio Annotation Platform.')
            ->line('Please set your password to get started.')
            ->action('Set Password', $resetUrl)
            ->line('This password reset link will expire in 60 minutes.') // or however long your broker tokens last
            ->line('If you did not expect to receive this email, please contact the administrator.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
        ];
    }
}