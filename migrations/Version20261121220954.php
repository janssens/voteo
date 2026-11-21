<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121220954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un propriétaire aux listes de contacts (repris des questions qui les utilisent, sinon le premier utilisateur)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_list ADD owner_id INT DEFAULT NULL');
        // Listes existantes : propriétaire de la plus ancienne question qui les utilise…
        $this->addSql('UPDATE contact_list l SET owner_id = (
            SELECT q.owner_id FROM question q
            JOIN question_contact_list ql ON ql.question_id = q.id
            WHERE ql.contact_list_id = l.id
            ORDER BY q.id LIMIT 1
        )');
        // … sinon le premier utilisateur créé.
        $this->addSql('UPDATE contact_list SET owner_id = (SELECT MIN(id) FROM "app_user") WHERE owner_id IS NULL');
        $this->addSql('ALTER TABLE contact_list ALTER owner_id SET NOT NULL');
        $this->addSql('ALTER TABLE contact_list ADD CONSTRAINT FK_6C377AE77E3C61F9 FOREIGN KEY (owner_id) REFERENCES "app_user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6C377AE77E3C61F9 ON contact_list (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_list DROP CONSTRAINT FK_6C377AE77E3C61F9');
        $this->addSql('DROP INDEX IDX_6C377AE77E3C61F9');
        $this->addSql('ALTER TABLE contact_list DROP owner_id');
    }
}
