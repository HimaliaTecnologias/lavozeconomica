<?php

namespace App\Entity\Platform;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'site_database_config')]
class SiteDatabaseConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Site::class, inversedBy: 'databaseConfig')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
    private Site $site;

    #[ORM\Column(length: 255)]
    private string $host;

    #[ORM\Column(options: ['default' => 3306])]
    private int $port = 3306;

    #[ORM\Column(length: 128)]
    private string $databaseName;

    #[ORM\Column(length: 128)]
    private string $username;

    #[ORM\Column(length: 512)]
    private string $passwordEncrypted;

    public function __construct(
        Site $site,
        string $host,
        string $databaseName,
        string $username,
        string $passwordEncrypted,
        int $port = 3306,
    ) {
        $this->site = $site;
        $this->host = $host;
        $this->port = $port;
        $this->databaseName = $databaseName;
        $this->username = $username;
        $this->passwordEncrypted = $passwordEncrypted;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function setSite(Site $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswordEncrypted(): string
    {
        return $this->passwordEncrypted;
    }

    public function setPasswordEncrypted(string $passwordEncrypted): self
    {
        $this->passwordEncrypted = $passwordEncrypted;

        return $this;
    }

    public function buildDsn(string $serverVersion = 'mariadb-11.4.0'): string
    {
        return sprintf(
            'mysql://%s:%s@%s:%d/%s?serverVersion=%s&charset=utf8mb4',
            rawurlencode($this->username),
            rawurlencode($this->passwordEncrypted),
            $this->host,
            $this->port,
            $this->databaseName,
            $serverVersion,
        );
    }
}
