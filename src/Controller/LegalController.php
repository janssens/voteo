<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Mail\OrganizerResolver;
use App\Privacy\PersonalData;
use App\Repository\AnswerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mentions légales, politique de confidentialité et gestion de leurs données par les membres.
 * Les informations légales viennent de config/packages/legal.yaml (variable Twig « legal »).
 */
final class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'legal_notice', methods: ['GET'])]
    public function notice(): Response
    {
        return $this->render('legal/notice.html.twig');
    }

    #[Route('/confidentialite', name: 'privacy_policy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    /**
     * Un membre consulte les données que l'organisateur a saisies à son sujet (droit d'accès),
     * et peut les supprimer (droits d'effacement et d'opposition), grâce à son lien personnel.
     */
    #[Route('/mes-donnees/{a_token}', name: 'contact_data', methods: ['GET', 'POST'])]
    public function contactData(Request $request, string $a_token, AnswerRepository $answers, PersonalData $personalData, OrganizerResolver $organizers): Response
    {
        /** @var Answer|null $answer */
        $answer = $answers->findOneBy(['token' => $a_token]);
        if (!$answer) {
            return $this->render('default/error.html.twig', [
                'message' => 'Ce lien ne correspond à aucune donnée : elles ont peut-être déjà été supprimées.',
            ], new Response(status: Response::HTTP_NOT_FOUND));
        }
        $contact = $answer->getContact();
        // Auteur de la liste, ou son organisation (nom et adresse de contact)
        $organizer = $contact->getList() ? $organizers->for($contact) : null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact_data_delete_'.$answer->getToken(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

                return $this->redirectToRoute('contact_data', ['a_token' => $a_token]);
            }
            $personalData->deleteContact($contact);

            return $this->render('legal/contact_data_deleted.html.twig', ['organizer' => $organizer]);
        }

        return $this->render('legal/contact_data.html.twig', [
            'contact' => $contact,
            'answer' => $answer,
            'organizer' => $organizer,
        ]);
    }
}
