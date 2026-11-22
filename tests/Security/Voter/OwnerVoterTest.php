<?php

namespace App\Tests\Security\Voter;

use App\Entity\Choice;
use App\Entity\Contact;
use App\Entity\Question;
use App\Entity\User;
use App\Security\Voter\OwnerVoter;
use App\Tests\Support\Entities;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class OwnerVoterTest extends TestCase
{
    use Entities;

    private function vote(?User $user, mixed $subject): int
    {
        $decisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $decisionManager->method('decide')->willReturnCallback(
            static fn ($token, array $attributes) => \in_array('ROLE_ADMIN', $token->getRoleNames(), true),
        );

        $token = null === $user ? new NullToken() : new UsernamePasswordToken($user, 'main', $user->getRoles());

        return (new OwnerVoter($decisionManager))->vote($token, $subject, [OwnerVoter::OWNER]);
    }

    public function testPersonalResourceIsReservedToItsAuthor(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob', 'bob@example.test');
        $question = $this->question($alice);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($alice, $question));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($bob, $question));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($bob, $this->contactList($alice)));
    }

    public function testOrganizationResourceIsOpenToEveryMemberOnly(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob', 'bob@example.test');
        $xavier = $this->user('Xavier', 'xavier@example.test');
        $organization = $this->organization('Asso', $alice, $bob);
        // Auteur : Alice ; Bob, simple membre, a les mêmes droits.
        $question = $this->question($alice, $organization);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($alice, $question));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($bob, $question));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($xavier, $question));
    }

    public function testFormerAuthorLosesAccessToOrganizationResource(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob', 'bob@example.test');
        $organization = $this->organization('Asso', $alice, $bob);
        $list = $this->contactList($alice, $organization);

        $organization->removeMembership($alice->getMemberships()->first());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($alice, $list));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($bob, $list));
    }

    public function testOwnerlessOrganizationResourceStaysAccessibleToMembers(): void
    {
        $bob = $this->user('Bob', 'bob@example.test');
        $question = $this->question(null, $this->organization('Asso', $bob));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($bob, $question));
    }

    public function testChildEntitiesFollowTheirParent(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob', 'bob@example.test');
        $organization = $this->organization('Asso', $alice);

        $choice = $this->withId((new Choice())->setLabel('Oui'));
        $this->question($alice, $organization)->addChoice($choice);

        $contact = $this->withId((new Contact())->setFirstname('Camille')->setLastname('M')->setEmail('c@example.test'));
        $this->contactList($alice)->addContact($contact);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($alice, $choice));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($bob, $choice));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($alice, $contact));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($bob, $contact));
    }

    public function testNewEntityIsAllowedAndAdminSeesEverything(): void
    {
        $bob = $this->user('Bob', 'bob@example.test');
        $admin = $this->user('Admin', 'admin@example.test', ['ROLE_ADMIN']);
        $question = $this->question($this->user('Alice'));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($bob, new Question()));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $question));
    }

    public function testAnonymousIsDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(null, $this->question($this->user())));
    }

    public function testOtherAttributesAreIgnored(): void
    {
        $voter = new OwnerVoter($this->createStub(AccessDecisionManagerInterface::class));
        $user = $this->user();

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote(new UsernamePasswordToken($user, 'main', []), $this->question($user), ['EDIT']),
        );
    }
}
