<?php

declare(strict_types=1);

namespace DoctrineMigrations\Platform;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Último acceso de usuarios (login)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE users ADD last_login_ip VARCHAR(45) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP last_login_ip');
        $this->addSql('ALTER TABLE users DROP last_login_at');
    }
}
