<?php

namespace App\Services\Notification;

use App\Http\Resources\TelegramAccountResource;
use App\Models\User;
use App\Models\UserMeta;
use Illuminate\Support\Facades\File;

class NotificationSettingsPresenter
{
    protected const DEFAULT_SOUND_TRACK = 'radwimps.mp3';

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $isTrader = $user->hasRole('Trader');
        $audioTracks = $isTrader ? $this->loadAudioTracks() : [];

        $telegramAccount = TelegramAccountResource::make(
            services()->telegram()->getOrCreateForUser($user)
        )->resolve();

        return [
            'telegramAccount' => $telegramAccount,
            'showInAppSoundSettings' => $isTrader,
            'audioTracks' => $audioTracks,
            'notificationSoundSettings' => $isTrader
                ? $this->buildNotificationSoundSettings($user->meta, $audioTracks)
                : [],
        ];
    }

    /**
     * @return array<string, array{enabled: bool, track: string|null}>
     */
    protected function buildNotificationSoundSettings(?UserMeta $meta, array $audioTracks): array
    {
        return [
            'order_assigned' => [
                'enabled' => $meta?->notification_sound_order_enabled ?? true,
                'track' => $this->resolveSoundTrack($meta?->notification_sound_order_track, $audioTracks),
            ],
            'dispute_opened' => [
                'enabled' => $meta?->notification_sound_dispute_enabled ?? true,
                'track' => $this->resolveSoundTrack($meta?->notification_sound_dispute_track, $audioTracks),
            ],
            'message_received' => [
                'enabled' => $meta?->notification_sound_message_enabled ?? true,
                'track' => $this->resolveSoundTrack($meta?->notification_sound_message_track, $audioTracks),
            ],
        ];
    }

    /**
     * @return list<array{name: string, value: string, url: string}>
     */
    public function getAudioTracks(): array
    {
        return $this->loadAudioTracks();
    }

    public function resolveSoundTrack(?string $track, array $audioTracks): ?string
    {
        if (empty($audioTracks)) {
            return null;
        }

        $allowedTracks = array_column($audioTracks, 'value');

        if ($track && in_array($track, $allowedTracks, true)) {
            return $track;
        }

        if (in_array(self::DEFAULT_SOUND_TRACK, $allowedTracks, true)) {
            return self::DEFAULT_SOUND_TRACK;
        }

        return $audioTracks[0]['value'];
    }

    /**
     * @return list<array{name: string, value: string, url: string}>
     */
    protected function loadAudioTracks(): array
    {
        $audioDirectory = public_path('audio');

        if (! File::isDirectory($audioDirectory)) {
            return [];
        }

        return collect(File::files($audioDirectory))
            ->filter(function ($file) {
                return $file->getExtension() === 'mp3';
            })
            ->sortBy(function ($file) {
                return $file->getFilename();
            })
            ->values()
            ->map(function ($file) {
                $name = $file->getFilename();

                return [
                    'name' => $name,
                    'value' => $name,
                    'url' => '/audio/'.$name,
                ];
            })
            ->toArray();
    }
}
