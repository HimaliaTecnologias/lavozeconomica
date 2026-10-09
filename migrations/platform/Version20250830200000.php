<?php

declare(strict_types=1);

namespace DoctrineMigrations\Platform;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250830200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Instalación: platform_state, users, user_site_roles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_state (
            id INT NOT NULL,
            installed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            primary_site_code VARCHAR(64) DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE users (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            is_platform_superadmin TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_USERS_EMAIL (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE user_site_roles (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            site_id INT NOT NULL,
            role VARCHAR(64) NOT NULL,
            INDEX IDX_USER_SITE_ROLES_USER (user_id),
            INDEX IDX_USER_SITE_ROLES_SITE (site_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE user_site_roles ADD CONSTRAINT FK_USER_SITE_ROLES_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_site_roles ADD CONSTRAINT FK_USER_SITE_ROLES_SITE FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');

        $this->addSql('INSERT INTO platform_state (id, installed_at, primary_site_code) VALUES (1, NULL, NULL)');

        $siteCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sites');
        if ($siteCount > 0) {
            $code = (string) $this->connection->fetchOne('SELECT code FROM sites ORDER BY id ASC LIMIT 1');
            $this->addSql(
                'UPDATE platform_state SET installed_at = NOW(), primary_site_code = ? WHERE id = 1',
                [$code],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_site_roles DROP FOREIGN KEY FK_USER_SITE_ROLES_SITE');
        $this->addSql('ALTER TABLE user_site_roles DROP FOREIGN KEY FK_USER_SITE_ROLES_USER');
        $this->addSql('DROP TABLE user_site_roles');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE platform_state');
    }
}
