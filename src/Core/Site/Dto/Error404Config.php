<?php

namespace App\Core\Site\Dto;

final readonly class Error404Config
{
    public function __construct(
        public string $title = 'Página no encontrada',
        public string $message = 'La dirección que buscas no existe, se ha movido o ya no está disponible.',
        public bool $showSearch = true,
        public bool $showHomeButton = true,
        public bool $showLatestArticles = true,
        public string $searchPlaceholder = 'Buscar noticias…',
        public string $homeButtonLabel = 'Ir a la portada',
        public string $extraHtml = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'showSearch' => $this->showSearch,
            'showHomeButton' => $this->showHomeButton,
            'showLatestArticles' => $this->showLatestArticles,
            'searchPlaceholder' => $this->searchPlaceholder,
            'homeButtonLabel' => $this->homeButtonLabel,
            'extraHtml' => $this->extraHtml,
        ];
    }
}
