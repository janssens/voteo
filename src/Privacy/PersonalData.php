<?php

namespace App\Privacy;

use App\Entity\Answer;
use App\Entity\Choice;
use App\Entity\Contact;
use App\Entity\ContactList;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\Question;
use App\Entity\User;
use App\Repository\ContactListRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Droits RGPD : export (accès, portabilité) et effacement des données,
 * pour les organisateurs (comptes) comme pour les membres (contacts).
 */
class PersonalData
{
    public function __construct(
        private EntityManagerInterface $em,
        private ContactListRepository $lists,
    ) {
    }

    /**
     * Listes personnelles de l'utilisateur (hors organisations).
     *
     * @return ContactList[]
     */
    public function personalLists(User $user): array
    {
        return $this->lists->findBy(['owner' => $user, 'organization' => null]);
    }

    /**
     * Questions personnelles de l'utilisateur (hors organisations).
     *
     * @return Question[]
     */
    public function personalQuestions(User $user): array
    {
        return array_values($user->getQuestions()->filter(static fn (Question $q) => null === $q->getOrganization())->toArray());
    }

    /**
     * Organisations dont l'utilisateur est le seul membre : elles bloquent la suppression du compte,
     * car elles se retrouveraient sans personne pour les gérer (même règle que pour quitter une organisation).
     *
     * @return Organization[]
     */
    public function organizationsBlockingDeletion(User $user): array
    {
        return array_values(array_filter(
            $user->getOrganizations(),
            static fn (Organization $o) => $o->getMemberships()->count() <= 1,
        ));
    }

    /**
     * Toutes les données d'un compte organisateur, dans un format structuré (art. 15 et 20 du RGPD) :
     * le compte, ses listes et questions personnelles, et ses appartenances aux organisations.
     * Les contenus des organisations appartiennent à l'organisation et n'y figurent pas.
     */
    public function exportUser(User $user): array
    {
        $date = static fn (?\DateTimeInterface $d) => $d?->format(\DateTimeInterface::ATOM);

        return [
            'exported_at' => $date(new \DateTimeImmutable()),
            'account' => [
                'username' => $user->getUsername(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'email' => $user->getEmail(),
                'email_verified' => $user->isVerified(),
                'privacy_accepted_at' => $date($user->getPrivacyAcceptedAt()),
            ],
            'contact_lists' => array_map(static fn (ContactList $list) => [
                'name' => $list->getName(),
                'contacts' => array_map(static fn (Contact $c) => [
                    'firstname' => $c->getFirstname(),
                    'lastname' => $c->getLastname(),
                    'email' => $c->getEmail(),
                ], $list->getContacts()->getValues()),
            ], $this->personalLists($user)),
            'organizations' => array_map(static fn (Membership $m) => [
                'name' => $m->getOrganization()?->getName(),
                'contact_email' => $m->getOrganization()?->getContactEmail(),
                'joined_at' => $date($m->getJoinedAt()),
                'invited_by' => $m->getInvitedBy()?->getFullName(),
            ], $user->getMemberships()->getValues()),
            'questions' => array_map(static fn (Question $q) => [
                'subject' => $q->getSubject(),
                'intro' => $q->getIntro(),
                'outro' => $q->getOutro(),
                'multiple_choices' => $q->isMultichoices(),
                'anonymous' => $q->isAnonymous(),
                'sent' => (bool) $q->isSent(),
                'closes_at' => $date($q->getClosesAt()),
                'lists' => array_map(static fn (ContactList $l) => $l->getName(), $q->getLists()->getValues()),
                'choices' => array_map(static fn (Choice $c) => $c->getLabel(), $q->getChoices()->getValues()),
                'answers' => array_map(static fn (Answer $a) => [
                    'contact' => (string) $a->getContact(),
                    'choices' => array_map(static fn (Choice $c) => $c->getLabel(), $a->getChoices()->getValues()),
                ], $q->getAnswers()->getValues()),
            ], $this->personalQuestions($user)),
        ];
    }

    /**
     * Supprime le compte et ses contenus personnels : questions (choix, réponses), listes et contacts.
     * Ses appartenances aux organisations disparaissent ; les listes et questions qu'il a créées dans une
     * organisation restent à l'organisation, sans auteur.
     *
     * L'appelant vérifie d'abord organizationsBlockingDeletion().
     */
    public function deleteUser(User $user): void
    {
        foreach ($user->getQuestions() as $question) {
            if (null === $question->getOrganization()) {
                $this->em->remove($question);
            } else {
                $question->setOwner(null);
            }
        }
        foreach ($this->lists->findBy(['owner' => $user]) as $list) {
            if (null === $list->getOrganization()) {
                $this->em->remove($list);
            } else {
                $list->setOwner(null);
            }
        }
        $this->em->remove($user);
        $this->em->flush();
    }

    /**
     * Supprime un membre (contact) et ses réponses, à sa demande (art. 17 et 21 du RGPD).
     */
    public function deleteContact(Contact $contact): void
    {
        $contact->getList()?->removeContact($contact);
        $this->em->remove($contact);
        $this->em->flush();
    }
}
