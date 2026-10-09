<?php

namespace App\Entity\Site;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'site_settings')]
#[ORM\UniqueConstraint(name: 'uniq_namespace_key', columns: ['namespace', 'key_name'])]
class SiteSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $namespace;

    #[ORM\Column(length: 128)]
    private string $keyName;

    #[ORM\Column(type: 'json')]
    private array $value;

    public function __construct(string $namespace, string $keyName, array $value)
    {
        $this->namespace = $namespace;
        $this->keyName = $keyName;
        $this->value = $value;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getKeyName(): string
    {
        return $this->keyName;
    }

    public function getValue(): array
    {
        return $this->value;
    }

    public function setValue(array $value): self
    {
        $this->value = $value;

        return $this;
    }
}
