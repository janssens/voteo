<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Live preview of the question email, rendered from the (unsaved) values of the admin question form.
 * Nothing is persisted: the template receives plain arrays instead of entities.
 */
final class EmailPreviewController extends AbstractController
{
    public const SAMPLE_CONTACT = ['firstname' => 'Camille', 'lastname' => 'Martin'];

    #[Route('/admin/apercu-email', name: 'admin_email_preview', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function preview(Request $request): Response
    {
        $data = $request->request->all('Question');

        $choices = [];
        foreach (array_values((array) ($data['choices'] ?? [])) as $i => $choice) {
            if (!is_array($choice)) {
                continue;
            }
            $choices[] = [
                'id' => $i + 1,
                'label' => trim((string) ($choice['label'] ?? '')) ?: 'Option '.($i + 1),
                'color' => self::color($choice['color'] ?? null),
                'textColor' => self::color($choice['textColor'] ?? null),
                'borderColor' => self::color($choice['borderColor'] ?? null),
            ];
        }

        $question = [
            'id' => 0,
            'subject' => (string) ($data['subject'] ?? ''),
            'intro' => (string) ($data['intro'] ?? ''),
            'outro' => (string) ($data['outro'] ?? ''),
            'multichoices' => !empty($data['is_multichoices']),
            'resultsVisibleBeforeVote' => !empty($data['resultsVisibleBeforeVote']),
            'anonymous' => !empty($data['anonymous']),
            'closesAt' => self::dateTime($data['closesAt'] ?? null),
        ];

        $response = $this->render('emails/first_send.html.twig', [
            'question' => $question,
            'choices' => $choices,
            'answer' => ['token' => 'apercu'],
            'contact' => self::SAMPLE_CONTACT,
            'preview' => true,
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /** Value of the native datetime-local input, typed in Paris time (same as the admin form field). */
    private static function dateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || '' === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', substr($value, 0, 16), new \DateTimeZone('Europe/Paris'));

        return $date ?: null;
    }

    /** Same formats as the Choice validation: only #rgb / #rrggbb reach the inline styles. */
    private static function color(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-f]{3}){1,2}$/i', $value) ? $value : null;
    }
}
