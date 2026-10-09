<?php

namespace App\Navigation;

enum NavMenuItemType: string
{
    case Home = 'home';
    case Edition = 'edition';
    case Search = 'search';
    case Categories = 'categories';
    case Category = 'category';
    case Weather = 'weather';
    case Markets = 'markets';
    case Tv = 'tv';
    case Games = 'games';
    case Horoscope = 'horoscope';
    case Agenda = 'agenda';
    case Classifieds = 'classifieds';
    case Forum = 'forum';
    case Services = 'services';
    case Page = 'page';
    case Custom = 'custom';
    case Login = 'login';
    case Register = 'register';
    case Profile = 'profile';
    case Logout = 'logout';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Portada',
            self::Edition => 'Edición',
            self::Search => 'Buscar',
            self::Categories => 'Categorías del menú',
            self::Category => 'Categoría',
            self::Weather => 'El tiempo',
            self::Markets => 'Bolsa',
            self::Tv => 'Programación TV',
            self::Games => 'Pasatiempos',
            self::Horoscope => 'Horóscopo',
            self::Agenda => 'Agenda',
            self::Classifieds => 'Avisos',
            self::Forum => 'Foro',
            self::Services => 'Servicios (grupo)',
            self::Page => 'Página del periódico',
            self::Custom => 'Enlace personalizado',
            self::Login => 'Entrar',
            self::Register => 'Registro',
            self::Profile => 'Mi cuenta',
            self::Logout => 'Salir',
            self::Admin => 'Admin',
        };
    }

    public function needsCategory(): bool
    {
        return $this === self::Category;
    }

    public function needsPage(): bool
    {
        return $this === self::Page;
    }

    public function needsUrl(): bool
    {
        return $this === self::Custom;
    }
}
