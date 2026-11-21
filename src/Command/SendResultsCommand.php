<?php

namespace App\Command;

use App\Entity\Answer;
use App\Entity\Question;
use App\Mail\Personalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends the final results to every recipient of the questions whose closing date has passed
 * and which have "Envoyer les résultats à la clôture" checked.
 * Meant to be run by cron every few minutes (crontab example in README.md).
 * Each question is sent at most once (resultsSentAt is claimed atomically before sending).
 */
#[AsCommand(name: 'app:send-results', description: 'Envoie les résultats des questions closes (option « Envoyer les résultats à la clôture »)')]
final class SendResultsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        #[Autowire('%app.transactional_mail_sender%')] private readonly string $sender,
        #[Autowire('%app.transactional_mail_sender_friendlyname%')] private readonly string $senderName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les questions concernées sans rien envoyer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        /** @var Question[] $questions */
        $questions = $this->em->getRepository(Question::class)->createQueryBuilder('q')
            ->andWhere('q.sendResultsOnClose = true')
            ->andWhere('q.sent = true')
            ->andWhere('q.resultsSentAt IS NULL')
            ->andWhere('q.closesAt IS NOT NULL')
            ->andWhere('q.closesAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        if (!$questions) {
            $io->writeln('Aucun résultat à envoyer.', OutputInterface::VERBOSITY_VERBOSE);

            return Command::SUCCESS;
        }

        $failures = 0;
        foreach ($questions as $question) {
            $answers = $question->getAnswers()->filter(static fn (Answer $a): bool => (bool) $a->getContact()?->getEmail());

            if ($dryRun) {
                $io->writeln(sprintf('#%d « %s » : %d destinataire(s)', $question->getId(), $question->getSubject(), count($answers)));
                continue;
            }

            // Atomic claim: if two runs overlap, only one of them sends the results of this question
            $claimed = $this->em->createQuery(
                'UPDATE App\Entity\Question q SET q.resultsSentAt = :now WHERE q.id = :id AND q.resultsSentAt IS NULL'
            )->setParameters(['now' => $now, 'id' => $question->getId()])->execute();
            if (1 !== $claimed) {
                continue;
            }

            $sent = 0;
            foreach ($answers as $answer) {
                $contact = $answer->getContact();
                $email = (new TemplatedEmail())
                    ->from(new Address($this->sender, $this->senderName))
                    ->to(new Address($contact->getEmail(), $contact->getFullName()))
                    ->replyTo($question->getOwner()->getEmail())
                    ->subject('Résultats : '.Personalizer::personalize($question->getSubject(), $contact, false))
                    ->htmlTemplate('emails/results.html.twig')
                    ->context([
                        'question' => $question,
                        'answer' => $answer,
                        'contact' => $contact,
                    ]);
                try {
                    $this->mailer->send($email);
                    ++$sent;
                } catch (TransportExceptionInterface $e) {
                    ++$failures;
                    $io->error(sprintf('#%d : échec pour %s : %s', $question->getId(), $contact->getEmail(), $e->getMessage()));
                }
            }

            $io->writeln(sprintf('#%d « %s » : résultats envoyés à %d destinataire(s)', $question->getId(), $question->getSubject(), $sent));
        }

        return $failures > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
