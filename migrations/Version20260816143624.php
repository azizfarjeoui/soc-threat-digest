<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816143624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fetch_run (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, ran_at DATETIME NOT NULL, status VARCHAR(50) NOT NULL, new_reports_count INTEGER NOT NULL, errors CLOB DEFAULT NULL)');
        $this->addSql('CREATE TABLE threat_report (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, source VARCHAR(50) NOT NULL, external_id VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB NOT NULL, severity VARCHAR(50) DEFAULT NULL, malware_family VARCHAR(255) DEFAULT NULL, published_at DATETIME NOT NULL, fetched_at DATETIME NOT NULL, source_url VARCHAR(1000) DEFAULT NULL, raw_payload CLOB NOT NULL, created_at DATETIME NOT NULL, fetch_run_id INTEGER DEFAULT NULL, CONSTRAINT FK_9952B94E4C7D049 FOREIGN KEY (fetch_run_id) REFERENCES fetch_run (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9952B94E4C7D049 ON threat_report (fetch_run_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_source_external_id ON threat_report (source, external_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE fetch_run');
        $this->addSql('DROP TABLE threat_report');
    }
}
