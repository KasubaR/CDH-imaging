<?php

namespace App\Enums;

enum Permission: string
{
    case ViewImages = 'view_images';
    case Upload = 'upload';
    case Send = 'send';
    case Receive = 'receive';
    case Download = 'download';
    case Forward = 'forward';

    public function label(): string
    {
        return match ($this) {
            self::ViewImages => 'View permitted images',
            self::Upload => 'Upload',
            self::Send => 'Send',
            self::Receive => 'Receive',
            self::Download => 'Download',
            self::Forward => 'Forward',
        };
    }
}
