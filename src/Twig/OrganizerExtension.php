<?php

namespace App\Twig;

use App\Mail\OrganizerResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * {{ organizer(question).name }}, {{ organizer(contact).replyToEmail }}, {{ organizer(question).senderName }}…
 * Voir App\Mail\OrganizerResolver.
 */
final class OrganizerExtension extends AbstractExtension
{
    public function __construct(private readonly OrganizerResolver $organizers)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('organizer', $this->organizers->for(...)),
        ];
    }
}
