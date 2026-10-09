<?php

declare(strict_types=1);

namespace DoctrineMigrations\Platform;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250901191500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Verificación de correo en registro de usuarios';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD email_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE users ADD email_verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD email_verification_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP email_verification_expires_at');
        $this->addSql('ALTER TABLE users DROP email_verification_token');
        $this->addSql('ALTER TABLE users DROP email_verified_at');
    }
}
