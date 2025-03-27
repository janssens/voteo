<?php
// src/EventListener/SearchIndexer.php
namespace App\EventListener;

use App\Entity\Answer;
use App\Entity\Choice;
use App\Entity\Contact;
use App\Entity\ContactList;
use App\Entity\Question;
use App\Event\SendQuestionEvent;
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

    public function __construct(MailerInterface $mailer,ParameterBagInterface $params,EntityManagerInterface $em)
    {
        $this->mailer = $mailer;
        $this->params = $params;
        $this->em = $em;
    }


    protected function sendQuestionEmail(Question $question){
        $choices = $this->em->getRepository(Choice::class)->findBy(['question'=> $question]);
        $answers = $this->em->getRepository(Answer::class)->findBy(['question' => $question]);

        $recipients = [];
        foreach( $question->getLists() as $contactList) {
            foreach( $contactList->getContacts() as $contact) {
                $recipients[] = $contact;
            }
        }

        $sortedReplies = [];
        /** @var Answer $answer */
        foreach ($answers as $answer){
            $sortedReplies[$answer->getContact()->getId()] = $answer;
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
        foreach ($recipients as $recipient) {
            $email = (new TemplatedEmail())
                ->from(new Address($this->params->get('app.transactional_mail_sender'), $this->params->get('app.transactional_mail_sender_friendlyname')))
                ->to(new Address($recipient->getEmail(), $recipient->getName()))
                //->cc('cc@example.com')
                //->bcc('bcc@example.com')
                //->replyTo('fabien@example.com')
                ->subject($question->getSubject())
                ->htmlTemplate('emails/first_send.html.twig')
                ->context([
                    'question' => $question,
                    'answer' => $sortedReplies[$recipient->getId()],
                    'choices' => $choices,
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
            $this->sendQuestionEmail($question);
            $question->setSent(true);
            $this->em->flush();
        }
    }

}