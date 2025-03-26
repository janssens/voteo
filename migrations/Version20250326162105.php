<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250326162105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE question_contact_list (question_id INT NOT NULL, contact_list_id INT NOT NULL, PRIMARY KEY(question_id, contact_list_id))');
        $this->addSql('CREATE INDEX IDX_FFDD81A51E27F6BF ON question_contact_list (question_id)');
        $this->addSql('CREATE INDEX IDX_FFDD81A5A781370A ON question_contact_list (contact_list_id)');
        $this->addSql('ALTER TABLE question_contact_list ADD CONSTRAINT FK_FFDD81A51E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE question_contact_list ADD CONSTRAINT FK_FFDD81A5A781370A FOREIGN KEY (contact_list_id) REFERENCES contact_list (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question_contact_list DROP CONSTRAINT FK_FFDD81A51E27F6BF');
        $this->addSql('ALTER TABLE question_contact_list DROP CONSTRAINT FK_FFDD81A5A781370A');
        $this->addSql('DROP TABLE question_contact_list');
    }
}
