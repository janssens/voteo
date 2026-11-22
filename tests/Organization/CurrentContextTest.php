<?php

namespace App\Tests\Organization;

use App\Entity\User;
use App\Organization\CurrentContext;
use App\Tests\Support\Entities;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CurrentContextTest extends TestCase
{
    use Entities;

    private RequestStack $requestStack;

    private function context(?User $user): CurrentContext
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);

        return new CurrentContext($security, $this->requestStack, $this->createStub(EntityManagerInterface::class));
    }

    /** Nouvelle requête de la même session (le service est réutilisé en mode worker). */
    private function nextRequest(CurrentContext $context): void
    {
        $context->reset();
    }

    public function testDefaultsToPersonal(): void
    {
        $context = $this->context($this->user());

        self::assertNull($context->getOrganization());
        self::assertTrue($context->isPersonal());
    }

    public function testAnonymousIsPersonal(): void
    {
        self::assertNull($this->context(null)->getOrganization());
    }

    public function testSwitchIsKeptInSessionAndRememberedOnTheAccount(): void
    {
        $alice = $this->user();
        $organization = $this->organization('Asso', $alice);
        $context = $this->context($alice);

        $context->switchTo($organization);
        $this->nextRequest($context);

        self::assertSame($organization, $context->getOrganization());
        self::assertSame($organization, $alice->getLastOrganization());
    }

    public function testNewSessionRestoresTheLastOrganization(): void
    {
        $alice = $this->user();
        $organization = $this->organization('Asso', $alice);
        $alice->setLastOrganization($organization);

        self::assertSame($organization, $this->context($alice)->getOrganization());
    }

    public function testExplicitPersonalChoiceWinsOverTheLastOrganization(): void
    {
        $alice = $this->user();
        $organization = $this->organization('Asso', $alice);
        $context = $this->context($alice);

        $context->switchTo($organization);
        $context->switchTo(null);
        $this->nextRequest($context);

        self::assertNull($context->getOrganization());
        self::assertNull($alice->getLastOrganization());
    }

    public function testRemovedMemberFallsBackToPersonal(): void
    {
        $alice = $this->user();
        $bob = $this->user('Bob', 'bob@example.test');
        $organization = $this->organization('Asso', $alice, $bob);
        $context = $this->context($bob);
        $context->switchTo($organization);

        $organization->removeMembership($bob->getMemberships()->first());
        $this->nextRequest($context);

        self::assertNull($context->getOrganization());
    }

    public function testCannotSwitchToAnOrganizationYouAreNotMemberOf(): void
    {
        $organization = $this->organization('Asso', $this->user());

        $this->expectException(\InvalidArgumentException::class);
        $this->context($this->user('Xavier', 'x@example.test'))->switchTo($organization);
    }

    /** Mode worker : la même instance sert la requête suivante, d'un autre utilisateur. */
    public function testResetForgetsThePreviousRequestUser(): void
    {
        $alice = $this->user();
        $organization = $this->organization('Asso', $alice);
        $alice->setLastOrganization($organization);
        $bob = $this->user('Bob', 'bob@example.test');

        $current = $alice;
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturnCallback(static function () use (&$current) {
            return $current;
        });
        $context = new CurrentContext($security, new RequestStack(), $this->createStub(EntityManagerInterface::class));

        self::assertSame($organization, $context->getOrganization());

        $current = $bob;
        $context->reset(); // appelé par le kernel entre deux requêtes
        self::assertNull($context->getOrganization());
    }

    public function testAvailableOrganizationsAreSortedByName(): void
    {
        $alice = $this->user();
        $this->organization('Zèbre', $alice);
        $this->organization('abeille', $alice);

        self::assertSame(['abeille', 'Zèbre'], array_map(
            static fn ($o) => $o->getName(),
            $this->context($alice)->getAvailableOrganizations(),
        ));
    }
}
