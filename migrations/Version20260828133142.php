<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260828133142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fetch_run ADD COLUMN processed_sources CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__fetch_run AS SELECT id, ran_at, status, new_reports_count, errors FROM fetch_run');
        $this->addSql('DROP TABLE fetch_run');
        $this->addSql('CREATE TABLE fetch_run (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, ran_at DATETIME NOT NULL, status VARCHAR(50) NOT NULL, new_reports_count INTEGER NOT NULL, errors CLOB DEFAULT NULL)');
        $this->addSql('INSERT INTO fetch_run (id, ran_at, status, new_reports_count, errors) SELECT id, ran_at, status, new_reports_count, errors FROM __temp__fetch_run');
        $this->addSql('DROP TABLE __temp__fetch_run');
    }
}
