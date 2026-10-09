<?php

namespace App\Entity\Site;

use App\Repository\Site\WeatherWebcamRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeatherWebcamRepository::class)]
#[ORM\Table(name: 'weather_webcams')]
class WeatherWebcam
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 1024)]
    private string $url;

    #[ORM\Column(length: 16, options: ['default' => 'embed'])]
    private string $type = 'embed';

    #[ORM\Column(name: 'sort_order', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function __construct(string $title, string $url, string $type = 'embed')
    {
        $this->title = $title;
        $this->url = $url;
        $this->type = $type;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEmbed(): bool
    {
        return $this->type === 'embed';
    }
}
