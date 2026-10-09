<?php

namespace App\Editorial;

final class SiteSocialNetworkRegistry
{
    /** @return list<array{key: string, label: string, placeholder: string, settingKey: string}> */
    public static function networks(): array
    {
        return [
            ['key' => 'facebook', 'label' => 'Facebook', 'placeholder' => 'https://facebook.com/...', 'settingKey' => 'facebook_url'],
            ['key' => 'twitter', 'label' => 'X (Twitter)', 'placeholder' => 'https://x.com/...', 'settingKey' => 'twitter_url'],
            ['key' => 'instagram', 'label' => 'Instagram', 'placeholder' => 'https://instagram.com/...', 'settingKey' => 'instagram_url'],
            ['key' => 'youtube', 'label' => 'YouTube', 'placeholder' => 'https://youtube.com/...', 'settingKey' => 'youtube_url'],
            ['key' => 'linkedin', 'label' => 'LinkedIn', 'placeholder' => 'https://linkedin.com/...', 'settingKey' => 'linkedin_url'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'placeholder' => 'https://wa.me/34...', 'settingKey' => 'whatsapp_url'],
            ['key' => 'tiktok', 'label' => 'TikTok', 'placeholder' => 'https://tiktok.com/@...', 'settingKey' => 'tiktok_url'],
            ['key' => 'telegram', 'label' => 'Telegram', 'placeholder' => 'https://t.me/...', 'settingKey' => 'telegram_url'],
        ];
    }

    /** @return list<string> */
    public static function urlFieldNames(): array
    {
        return array_map(
            static fn (array $n): string => $n['key'].'Url',
            self::networks(),
        );
    }
}
