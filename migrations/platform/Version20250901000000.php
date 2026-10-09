<?php

declare(strict_types=1);

namespace DoctrineMigrations\Platform;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250901000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Historial de importaciones HimtecMedia (ingest_fetch_runs)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ingest_fetch_runs (
            id INT AUTO_INCREMENT NOT NULL,
            site_id INT NOT NULL,
            source VARCHAR(64) DEFAULT \'himtecmedia\' NOT NULL,
            trigger_type VARCHAR(32) NOT NULL,
            ran_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            request_url VARCHAR(1024) DEFAULT NULL,
            feed_total INT DEFAULT 0 NOT NULL,
            received INT DEFAULT 0 NOT NULL,
            duplicates INT DEFAULT 0 NOT NULL,
            published INT DEFAULT 0 NOT NULL,
            errors INT DEFAULT 0 NOT NULL,
            success TINYINT(1) DEFAULT 1 NOT NULL,
            status_message LONGTEXT NOT NULL,
            error_messages JSON NOT NULL,
            items JSON NOT NULL,
            INDEX idx_ingest_fetch_site_ran (site_id, ran_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE ingest_fetch_runs ADD CONSTRAINT FK_INGEST_FETCH_RUNS_SITE FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingest_fetch_runs DROP FOREIGN KEY FK_INGEST_FETCH_RUNS_SITE');
        $this->addSql('DROP TABLE ingest_fetch_runs');
    }
}
