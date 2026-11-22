<?php
// src/EventListener/SearchIndexer.php
namespace App\EventListener;

use App\Entity\Answer;
use App\Entity\Choice;
use App\Entity\Contact;
use App\Entity\ContactList;
use App\Entity\Question;
use App\Event\SendQuestionEvent;
use App\Mail\OrganizerResolver;
use App\Mail\Personalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class QuestionEmailer
{

    private $mailer;
    private $params;
    private $em;

    public function __construct(MailerInterface $mailer,ParameterBagInterface $params,EntityManagerInterface $em, private readonly OrganizerResolver $organizers)
    {
        $this->mailer = $mailer;
        $this->params = $params;
        $this->em = $em;
    }


    protected function sendQuestionEmail(Question $question, bool $force = false){
        $choices = $this->em->getRepository(Choice::class)->findBy(['question'=> $question]);
        $answers = $this->em->getRepository(Answer::class)->findBy(['question' => $question]);

        $recipients = [];
        foreach( $question->getLists() as $contactList) {
            foreach( $contactList->getContacts() as $contact) {
                $recipients[$contact->getId()] = $contact;
            }
        }

        $sortedReplies = [];
        /** @var Answer $answer */
        foreach ($answers as $answer) {
            $contact_id = $answer->getContact()->getId();
            $sortedReplies[$contact_id] = $answer;
            //do not resent mail if reply exist, exept force
            if ( !$force && count($answer->getChoices()) >= 1 ) {
                unset($recipients[$contact_id]);
            }
        }
        /** @var Contact $recipient */
        foreach ($recipients as $recipient) {
            if (!isset($sortedReplies[$recipient->getId()])) {
                $answer = new Answer();
                $answer->setQuestion($question);
                $answer->setContact($recipient);
                $this->em->persist($answer);
                $sortedReplies[$recipient->getId()] = $answer;
            }
        }
        $this->em->flush();
        // « Prénom via Voteo » ou « Organisation via Voteo », réponses à l'auteur ou à l'organisation
        $organizer = $this->organizers->for($question);
        /** @var Contact $recipient */
        foreach ($recipients as $recipient) {
            $email = $this->organizers->address(new TemplatedEmail(), $organizer)
                ->to(new Address($recipient->getEmail(), $recipient->getFullName()))
                ->subject(Personalizer::personalize($question->getSubject(), $recipient, false))
                ->htmlTemplate('emails/first_send.html.twig')
                ->textTemplate('emails/first_send.txt.twig')
                ->context([
                    'question' => $question,
                    'answer' => $sortedReplies[$recipient->getId()],
                    'choices' => $choices,
                    'contact' => $recipient,
                ]);
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                var_dump($e);
                die();
            }
        }
    }

    public function sendEmail(SendQuestionEvent $event): void
    {
        $question = $event->getQuestion();

        if (!$question->isSent()){
            $this->sendQuestionEmail($question);
            $question->setSent(true);
            $this->em->flush();
        }
    }

    public function sendEmailAgain(SendQuestionEvent $event): void
    {
        $question = $event->getQuestion();

        if ($question->isSent()){
            $this->sendQuestionEmail($question, false);
            $question->setSent(true);
            $this->em->flush();
        }
    }

}