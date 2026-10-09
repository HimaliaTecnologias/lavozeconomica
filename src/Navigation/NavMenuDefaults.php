<?php

namespace App\Navigation;

final class NavMenuDefaults
{
    /** @return list<array<string, mixed>> */
    public static function mainItems(): array
    {
        return [
            self::item('home', NavMenuItemType::Home, 'Portada', 10),
            self::item('edition', NavMenuItemType::Edition, 'Edición', 20),
            self::item('search', NavMenuItemType::Search, 'Buscar', 30, enabled: false),
            self::item('categories', NavMenuItemType::Categories, '', 40),
            self::item('agenda', NavMenuItemType::Agenda, 'Agenda', 45),
            self::item('services', NavMenuItemType::Services, 'Servicios', 50),
            self::item('weather', NavMenuItemType::Weather, 'El tiempo', 55, enabled: false),
            self::item('markets', NavMenuItemType::Markets, 'Bolsa', 60, enabled: false),
            self::item('tv', NavMenuItemType::Tv, 'Programación TV', 65, enabled: false),
            self::item('games', NavMenuItemType::Games, 'Pasatiempos', 66, enabled: false),
            self::item('horoscope', NavMenuItemType::Horoscope, 'Horóscopo', 67),
            self::item('classifieds', NavMenuItemType::Classifieds, 'Avisos', 68, enabled: false),
            self::item('forum', NavMenuItemType::Forum, 'Foro', 69, enabled: false),
            self::item('login', NavMenuItemType::Login, 'Entrar', 70, enabled: false),
            self::item('register', NavMenuItemType::Register, 'Registro', 80, enabled: false),
            self::item('profile', NavMenuItemType::Profile, 'Mi cuenta', 85, enabled: false),
            self::item('logout', NavMenuItemType::Logout, 'Salir', 86, enabled: false),
            self::item('admin', NavMenuItemType::Admin, 'Admin', 90, enabled: false),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function footerSectionItems(): array
    {
        return [
            self::item('footer-home', NavMenuItemType::Home, 'Portada', 10),
            self::item('footer-categories', NavMenuItemType::Categories, '', 20),
            self::item('footer-classifieds', NavMenuItemType::Classifieds, 'Avisos', 30),
            self::item('footer-forum', NavMenuItemType::Forum, 'Foro', 40),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function footerLegalItems(): array
    {
        return [
            self::item('legal-quienes', NavMenuItemType::Page, 'Quiénes somos', 10, pageSlug: 'quienes-somos'),
            self::item('legal-contacto', NavMenuItemType::Page, 'Contacto', 20, pageSlug: 'contacto'),
            self::item('legal-aviso', NavMenuItemType::Page, 'Aviso legal', 30, pageSlug: 'aviso-legal'),
            self::item('legal-privacidad', NavMenuItemType::Page, 'Privacidad', 40, pageSlug: 'privacidad'),
            self::item('legal-cookies', NavMenuItemType::Page, 'Política de cookies', 50, pageSlug: 'cookies'),
            self::item('legal-anunciantes', NavMenuItemType::Page, 'Anunciantes', 60, pageSlug: 'anunciantes'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(
        string $id,
        NavMenuItemType $type,
        string $label,
        int $sortOrder,
        ?string $pageSlug = null,
        bool $enabled = true,
    ): array {
        return [
            'id' => $id,
            'type' => $type->value,
            'label' => $label,
            'enabled' => $enabled,
            'sortOrder' => $sortOrder,
            'categoryId' => null,
            'pageSlug' => $pageSlug,
            'url' => '',
            'openInNewTab' => false,
        ];
    }
}
