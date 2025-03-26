<?php
// src/EventListener/SearchIndexer.php
namespace App\EventListener;

use App\Entity\Contact;
use App\Entity\Question;
use App\Entity\Reply;
use App\Event\SendRaceEmailEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class QuestionEmailer implements EventSubscriberInterface
{

    private $mailer;
    private $params;
    private $em;
    private $objectManager;
    public function __construct(MailerInterface $mailer,ParameterBagInterface $params,EntityManagerInterface $em)
    {
        $this->mailer = $mailer;
        $this->params = $params;
        $this->em = $em;
    }

    public static function getSubscribedEvents() :array
    {
        return [
            AfterEntityPersistedEvent::class => ['AfterEntityPersistedEvent']
        ];
    }

    protected function sendQuestionEmail(Question $question){
        $recipients = $this->em->getRepository(Contact::class)->findBy(['is_active'=>true]);
        $questions = $this->em->getRepository(Question::class)->findBy(['is_active'=>true]);
        $replies = $this->em->getRepository(Reply::class)->findBy(['race' => $race]);
        $sortedReplies = [];
        /** @var Reply $reply */
        foreach ($replies as $reply){
            $sortedReplies[$reply->getInstructor()->getId()] = $reply;
        }
        /** @var Instructor $instructor */
        foreach ($instructors as $instructor) {
            if (!isset($sortedReplies[$instructor->getId()])) {
                $reply = new Reply();
                $reply->setRace($race);
                $reply->setInstructor($instructor);
                $this->em->persist($reply);
                $sortedReplies[$instructor->getId()] = $reply;
            }
        }
        $this->em->flush();
        foreach ($instructors as $instructor) {
            $email = (new TemplatedEmail())
                ->from(new Address($this->params->get('app.transactional_mail_sender'), $this->params->get('app.transactional_mail_sender_friendlyname')))
                ->to(new Address($instructor->getEmail(), $instructor->getName()))
                //->cc('cc@example.com')
                //->bcc('bcc@example.com')
                //->replyTo('fabien@example.com')
                ->subject('[FFTRI Raid] Une nouvelle épreuve à instruire : '.$race->getDisplayName())
                ->htmlTemplate('emails/new_race.html.twig')
                ->context([
                    'race' => $race,
                    'reply' => $sortedReplies[$instructor->getId()],
                    'questions' => $questions,
                ]);
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                var_dump($e);
                die();
            }
        }
    }

    public function sendEmail(SendRaceEmailEvent $event): void
    {
        $race = $event->getRace();

        $today = new \DateTime('now');

        /** @var Race $entity */
        if (!$race->isEmailSent() && $race->getDate() > $today){
            $this->sendNewRaceEmail($race);
            $race->setEmailSent(true);
            $this->em->flush();
        }
    }

    public function forceSendEmail(SendRaceEmailEvent $event): void
    {
        $race = $event->getRace();
        $this->sendNewRaceEmail($race);
        $race->setEmailSent(true);
        $this->em->flush();
    }

    public function AfterEntityPersistedEvent(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!$entity instanceof Race) {
            return;
        }

        $today = new \DateTime('now');
        /** @var Race $entity */
        if (!$entity->isEmailSent() && $entity->getDate() > $today){
            $this->sendNewRaceEmail($entity);
            $entity->setEmailSent(true);
            $this->em->flush();
        }

    }
}