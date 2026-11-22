<?php

namespace App\Privacy;

use App\Entity\Answer;
use App\Entity\Choice;
use App\Entity\Contact;
use App\Entity\ContactList;
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
     * Toutes les données d'un compte organisateur, dans un format structuré (art. 15 et 20 du RGPD).
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
            ], $this->lists->findBy(['owner' => $user])),
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
            ], $user->getQuestions()->getValues()),
        ];
    }

    /**
     * Supprime le compte et tout ce qui lui appartient : questions (choix, réponses), listes et contacts.
     */
    public function deleteUser(User $user): void
    {
        foreach ($user->getQuestions() as $question) {
            $this->em->remove($question);
        }
        foreach ($this->lists->findBy(['owner' => $user]) as $list) {
            $this->em->remove($list);
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
