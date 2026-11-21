<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121220828 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la couleur du texte et de la bordure aux choix';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE choice ADD text_color VARCHAR(15) DEFAULT NULL');
        $this->addSql('ALTER TABLE choice ADD border_color VARCHAR(15) DEFAULT NULL');
        // Existing choices keep their background and get the default text / border colors
        $this->addSql("UPDATE choice SET text_color = '#2f2b45', border_color = '#e4e0f5'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE choice DROP text_color');
        $this->addSql('ALTER TABLE choice DROP border_color');
    }
}
