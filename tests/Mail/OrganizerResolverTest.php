<?php

namespace App\Tests\Mail;

use App\Entity\Choice;
use App\Entity\Contact;
use App\Mail\OrganizerResolver;
use App\Tests\Support\Entities;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

final class OrganizerResolverTest extends TestCase
{
    use Entities;

    private OrganizerResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OrganizerResolver('no-reply@voteo.test', 'Voteo');
    }

    public function testPersonalQuestionIsSentAsAuthorFirstname(): void
    {
        $organizer = $this->resolver->for($this->question($this->user('Gaëtan', 'gaetan@example.test')));

        self::assertSame('Gaëtan via Voteo', $organizer->getSenderName());
        self::assertSame('Gaëtan Test', $organizer->name);
        self::assertSame('gaetan@example.test', $organizer->replyToEmail);
        self::assertFalse($organizer->isOrganization);
    }

    public function testOrganizationQuestionIsSentAsOrganizationWithContactEmail(): void
    {
        $organization = $this->organization('Asso Jardins partagés', $this->user());
        $organizer = $this->resolver->for($this->question($this->user('Bob', 'bob@example.test'), $organization));

        self::assertSame('Asso Jardins partagés via Voteo', $organizer->getSenderName());
        self::assertSame('Asso Jardins partagés', $organizer->name);
        self::assertSame('bureau@jardins.test', $organizer->replyToEmail);
        self::assertTrue($organizer->isOrganization);
    }

    public function testOwnerlessOrganizationResourceKeepsTheOrganization(): void
    {
        $organization = $this->organization('Asso', $this->user());

        self::assertSame('Asso via Voteo', $this->resolver->for($this->contactList(null, $organization))->getSenderName());
    }

    public function testContactAndChoiceResolveThroughTheirParent(): void
    {
        $organization = $this->organization('Asso', $this->user());

        $contact = (new Contact())->setFirstname('Camille')->setLastname('M')->setEmail('c@example.test');
        $this->contactList($this->user(), $organization)->addContact($contact);

        $choice = (new Choice())->setLabel('Oui');
        $this->question($this->user('Paul', 'paul@example.test'))->addChoice($choice);

        self::assertSame('Asso', $this->resolver->for($contact)->name);
        self::assertSame('Paul via Voteo', $this->resolver->for($choice)->getSenderName());
    }

    public function testAddressSetsSiteAddressWithOrganizerNameAndReplyTo(): void
    {
        $organization = $this->organization('Asso', $this->user());
        $email = $this->resolver->address(new TemplatedEmail(), $this->resolver->for($this->question(null, $organization)));

        self::assertSame('no-reply@voteo.test', $email->getFrom()[0]->getAddress());
        self::assertSame('Asso via Voteo', $email->getFrom()[0]->getName());
        self::assertSame('bureau@jardins.test', $email->getReplyTo()[0]->getAddress());
        self::assertSame('Asso', $email->getReplyTo()[0]->getName());
    }

    public function testHeaderInjectionInOrganizationNameIsNeutralized(): void
    {
        $organization = $this->organization("Asso\r\nBcc: victim@example.test", $this->user());
        $email = $this->resolver->address(new TemplatedEmail(), $this->resolver->for($this->question(null, $organization)));

        self::assertStringNotContainsString("\n", $email->getFrom()[0]->getName());
        self::assertSame([], $email->getBcc());
    }

    public function testInvitationIsSentAsTheInviter(): void
    {
        $organizer = $this->resolver->forUser($this->user('Alice', 'alice@example.test'));

        self::assertSame('Alice via Voteo', $organizer->getSenderName());
        self::assertSame('alice@example.test', $organizer->replyToEmail);
    }
}
