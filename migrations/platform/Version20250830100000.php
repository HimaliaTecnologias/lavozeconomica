<?php

declare(strict_types=1);

namespace DoctrineMigrations\Platform;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250830100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Esquema inicial BD plataforma';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sites (
            id INT AUTO_INCREMENT NOT NULL,
            code VARCHAR(64) NOT NULL,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(16) NOT NULL,
            default_locale VARCHAR(8) DEFAULT \'es\' NOT NULL,
            timezone VARCHAR(64) DEFAULT \'Europe/Madrid\' NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_SITES_CODE (code),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE site_domains (
            id INT AUTO_INCREMENT NOT NULL,
            site_id INT NOT NULL,
            domain VARCHAR(255) NOT NULL,
            is_primary TINYINT(1) DEFAULT 0 NOT NULL,
            UNIQUE INDEX UNIQ_SITE_DOMAINS_DOMAIN (domain),
            INDEX IDX_SITE_DOMAINS_SITE (site_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE site_database_config (
            id INT AUTO_INCREMENT NOT NULL,
            site_id INT NOT NULL,
            host VARCHAR(255) NOT NULL,
            port INT DEFAULT 3306 NOT NULL,
            database_name VARCHAR(128) NOT NULL,
            username VARCHAR(128) NOT NULL,
            password_encrypted VARCHAR(512) NOT NULL,
            UNIQUE INDEX UNIQ_SITE_DB_CONFIG_SITE (site_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE ingested_stories (
            id INT AUTO_INCREMENT NOT NULL,
            source VARCHAR(64) DEFAULT \'himtecmedia\' NOT NULL,
            external_id VARCHAR(255) NOT NULL,
            format_version VARCHAR(16) NOT NULL,
            title VARCHAR(512) NOT NULL,
            body_html LONGTEXT NOT NULL,
            raw_payload JSON NOT NULL,
            content_hash VARCHAR(64) NOT NULL,
            geo_json JSON DEFAULT NULL,
            classification_json JSON DEFAULT NULL,
            status VARCHAR(32) NOT NULL,
            received_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_source_external (source, external_id),
            INDEX idx_content_hash (content_hash),
            INDEX idx_status_received (status, received_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE site_publications (
            id INT AUTO_INCREMENT NOT NULL,
            site_id INT NOT NULL,
            ingested_story_id INT NOT NULL,
            article_id INT DEFAULT NULL,
            status VARCHAR(32) NOT NULL,
            auto_published TINYINT(1) DEFAULT 0 NOT NULL,
            assigned_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_site_ingested (site_id, ingested_story_id),
            INDEX IDX_SITE_PUBLICATIONS_SITE (site_id),
            INDEX IDX_SITE_PUBLICATIONS_INGESTED (ingested_story_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE site_domains ADD CONSTRAINT FK_SITE_DOMAINS_SITE FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE site_database_config ADD CONSTRAINT FK_SITE_DB_CONFIG_SITE FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE site_publications ADD CONSTRAINT FK_SITE_PUBLICATIONS_SITE FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE site_publications ADD CONSTRAINT FK_SITE_PUBLICATIONS_INGESTED FOREIGN KEY (ingested_story_id) REFERENCES ingested_stories (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_publications DROP FOREIGN KEY FK_SITE_PUBLICATIONS_INGESTED');
        $this->addSql('ALTER TABLE site_publications DROP FOREIGN KEY FK_SITE_PUBLICATIONS_SITE');
        $this->addSql('ALTER TABLE site_database_config DROP FOREIGN KEY FK_SITE_DB_CONFIG_SITE');
        $this->addSql('ALTER TABLE site_domains DROP FOREIGN KEY FK_SITE_DOMAINS_SITE');
        $this->addSql('DROP TABLE site_publications');
        $this->addSql('DROP TABLE ingested_stories');
        $this->addSql('DROP TABLE site_database_config');
        $this->addSql('DROP TABLE site_domains');
        $this->addSql('DROP TABLE sites');
    }
}
