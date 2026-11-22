<?php

namespace App\Command;

use App\Repository\InvitationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RGPD : supprime les invitations à une organisation expirées depuis plus de 30 jours.
 * Une invitation expirée reste d'abord visible (et renvoyable) dans la fiche de l'organisation,
 * puis l'adresse de la personne invitée est effacée. À lancer chaque jour par cron (voir README.md).
 */
#[AsCommand(name: 'app:purge-invitations', description: 'Supprime les invitations expirées depuis plus de 30 jours')]
final class PurgeInvitationsCommand extends Command
{
    public const RETENTION_AFTER_EXPIRY = '-30 days';

    public function __construct(private readonly InvitationRepository $invitations)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $before = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify(self::RETENTION_AFTER_EXPIRY);
        $deleted = $this->invitations->deleteExpiredBefore($before);

        (new SymfonyStyle($input, $output))->writeln(
            sprintf('%d invitation(s) expirée(s) supprimée(s).', $deleted),
            $deleted > 0 ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE,
        );

        return Command::SUCCESS;
    }
}
