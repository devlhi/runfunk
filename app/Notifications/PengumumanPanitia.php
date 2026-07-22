<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PengumumanPanitia extends Notification
{
    use Queueable;

    public function __construct(private readonly Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $namaAcara = Setting::ambil('event_name') ?: 'Gong Fun Run 2026';

        $mail = (new MailMessage)
            ->subject(($this->announcement->level === Announcement::LEVEL_PENTING ? '[PENTING] ' : '')
                .$this->announcement->title)
            ->greeting('Halo, '.$notifiable->name)
            ->line($this->announcement->body)
            ->action('Buka Dashboard Peserta', url('/dashboard'))
            ->salutation("Salam,\nPanitia {$namaAcara}");

        return $mail;
    }
}
