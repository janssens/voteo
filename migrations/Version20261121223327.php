<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121223327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Envoi automatique des résultats à la clôture d\'une question';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question ADD send_results_on_close BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE question ADD results_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question DROP send_results_on_close');
        $this->addSql('ALTER TABLE question DROP results_sent_at');
    }
}
