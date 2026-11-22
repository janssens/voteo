<?php

namespace App\Tests\Organization;

use App\Entity\Invitation;
use App\Organization\InvitationException;
use App\Organization\InvitationMailer;
use App\Organization\LastMemberException;
use App\Organization\OrganizationManager;
use App\Mail\OrganizerResolver;
use App\Tests\Support\Entities;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OrganizationManagerTest extends TestCase
{
    use Entities;

    /** @var list<TemplatedEmail> */
    private array $sent = [];

    private function manager(): OrganizationManager
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (RawMessage $message): void {
            $this->sent[] = $message;
        });
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $params) => 'https://voteo.test/invitation/'.$params['token']);

        $invitationMailer = new InvitationMailer($mailer, $urls, new OrganizerResolver('no-reply@voteo.test', 'Voteo'), 'Voteo');

        return new OrganizationManager($this->createStub(EntityManagerInterface::class), $invitationMailer);
    }

    public function testLastMemberCannotLeave(): void
    {
        $alice = $this->user();
        $organization = $this->organization('Asso', $alice);
        $membership = $alice->getMemberships()->first();

        self::assertFalse($this->manager()->canRemove($membership));
        $this->expectException(LastMemberException::class);
        $this->manager()->removeMember($membership);
    }

    public function testRemovingAMemberClearsBothSidesAndTheirLastContext(): void
    {
        $alice = $this->user();
        $bob = $this->user('Bob', 'bob@example.test');
        $organization = $this->organization('Asso', $alice, $bob);
        $bob->setLastOrganization($organization);

        $this->manager()->removeMember($bob->getMemberships()->first());

        self::assertFalse($organization->hasMember($bob));
        self::assertSame([], $bob->getOrganizations());
        self::assertNull($bob->getLastOrganization());
        self::assertTrue($organization->hasMember($alice));
    }

    public function testInviteSendsEmailAsInviterWithWorkingLink(): void
    {
        $alice = $this->user('Alice', 'alice@example.test');
        $organization = $this->organization('Asso Jardins partagés', $alice);

        $invitation = $this->manager()->invite($organization, '  Bob@Example.TEST ', $alice);

        self::assertSame('bob@example.test', $invitation->getEmail());
        self::assertCount(1, $this->sent);
        $email = $this->sent[0];
        self::assertSame('Alice via Voteo', $email->getFrom()[0]->getName());
        self::assertSame('alice@example.test', $email->getReplyTo()[0]->getAddress());
        self::assertSame('bob@example.test', $email->getTo()[0]->getAddress());

        // Le lien de l'email contient le jeton en clair ; seule son empreinte est stockée.
        $token = basename($email->getContext()['invitation_url']);
        self::assertSame(Invitation::hashToken($token), $invitation->getTokenHash());
        self::assertNotSame($token, $invitation->getTokenHash());
    }

    public function testReinvitingRenewsTheSameInvitationAndInvalidatesTheOldLink(): void
    {
        $alice = $this->user();
        $organization = $this->organization('Asso', $alice);
        $manager = $this->manager();

        $first = $manager->invite($organization, 'bob@example.test', $alice);
        $oldHash = $first->getTokenHash();
        $second = $manager->invite($organization, 'BOB@example.test', $alice);

        self::assertSame($first, $second);
        self::assertCount(1, $organization->getInvitations());
        self::assertNotSame($oldHash, $second->getTokenHash());
        self::assertCount(2, $this->sent);
    }

    public function testCannotInviteAnExistingMember(): void
    {
        $alice = $this->user('Alice', 'alice@example.test');
        $organization = $this->organization('Asso', $alice);

        $this->expectException(InvitationException::class);
        $this->manager()->invite($organization, 'ALICE@example.test', $alice);
    }

    public function testPendingInvitationsAreCapped(): void
    {
        $alice = $this->user();
        $organization = $this->organization('Asso', $alice);
        for ($i = 0; $i < OrganizationManager::MAX_PENDING_INVITATIONS; ++$i) {
            $organization->getInvitations()->add(new Invitation($organization, "p$i@example.test", $alice));
        }

        $this->expectException(InvitationException::class);
        $this->manager()->invite($organization, 'one-too-many@example.test', $alice);
    }

    public function testAcceptAddsTheMemberAndConsumesTheInvitation(): void
    {
        $alice = $this->user();
        $bob = $this->user('Bob', 'bob@example.test');
        $organization = $this->organization('Asso', $alice);
        $invitation = $this->manager()->invite($organization, 'bob@example.test', $alice);

        self::assertSame($organization, $this->manager()->accept($invitation, $bob));
        self::assertTrue($organization->hasMember($bob));
        self::assertSame([$organization], $bob->getOrganizations());
        self::assertSame($alice, $bob->getMemberships()->first()->getInvitedBy());
        self::assertCount(0, $organization->getInvitations());
    }

    public function testAcceptingTwiceDoesNotDuplicateTheMembership(): void
    {
        $alice = $this->user();
        $organization = $this->organization('Asso', $alice);
        $invitation = $this->manager()->invite($organization, 'alice2@example.test', $alice);

        $this->manager()->accept($invitation, $alice);

        self::assertCount(1, $organization->getMemberships());
    }

    public function testExpiredInvitationCannotBeAccepted(): void
    {
        $alice = $this->user();
        $organization = $this->organization('Asso', $alice);
        $invitation = $this->manager()->invite($organization, 'bob@example.test', $alice);
        (new \ReflectionProperty($invitation, 'expiresAt'))->setValue($invitation, new \DateTimeImmutable('-1 minute'));

        $this->expectException(InvitationException::class);
        $this->manager()->accept($invitation, $this->user('Bob', 'bob@example.test'));
    }
}
