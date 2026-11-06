<?php

namespace FancyCrud\Contracts;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class BaseNotification extends Notification
{
    use Queueable;

    private array $data;

    private string $messageEn;

    private string $messageAR;

    private string $title;

    private string $resource;

    private mixed $resourceId;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->messageEn = '';
        $this->messageAR = '';
        $this->title = '';
        $this->resource = '';
        $this->resourceId = 0;
    }

    public function resource(string $resource): static
    {
        $this->resource = $resource;
        $this->data = array_merge(['resource' => $this->resource], $this->data);

        return $this;
    }

    public function resourceId(mixed $resourceId): static
    {
        $this->resourceId = $resourceId;
        $this->data = array_merge(['resource_id' => $this->resourceId], $this->data);

        return $this;
    }

    public function title(string $title = 'New notification'): self
    {
        $this->title = $title;
        $this->data = array_merge(['title' => $this->title], $this->data);

        return $this;
    }

    /**
     * Every notification is stored in the "notifications" table (the in-app
     * list); it is also pushed through FCM when the notifiable has a device
     * token registered (User::routeNotificationForFcm()).
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->routeNotificationFor('fcm', $this))) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function data(array $data): static
    {
        $this->data = [
            ...$this->data,
            ...$data,
        ];

        return $this;
    }

    public function type(string $type = BaseNotification::class): static
    {
        $type = str_replace('App\\Notifications\\', '', $type);
        $this->data = array_merge(['type' => $type], $this->data);

        return $this;
    }

    public function messageEn(string $message): static
    {
        $this->messageEn = $message;
        $this->data = array_merge(['message_en' => $this->messageEn], $this->data);

        return $this;
    }

    public function messageAr(string $message): static
    {
        $this->messageAR = $message;
        $this->data = array_merge(['message_ar' => $this->messageAR], $this->data);

        return $this;
    }

    public function toDatabase(): array
    {
        return $this->data;
    }

    public function toFcm(): FcmMessage
    {
        return
            (new FcmMessage(
                notification: new FcmNotification(
                    title: $this->title,
                    body: $this->messageEn ?: $this->messageAR,
                )
            ))->data([
                'data' => json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'title' => $this->title,
                'message_en' => $this->messageEn,
                'message_ar' => $this->messageAR,
                'type' => static::class,
                'resource' => "$this->resource",
                'resource_id' => "$this->resourceId",
            ]);
    }
}
