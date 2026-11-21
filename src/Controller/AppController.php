<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Choice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RequestContext;

final class AppController extends AbstractController
{
    private const INVALID_LINK_MESSAGE = 'L&rsquo;url que vous utilisez ne correspond pas à une réponse possible';

    private $request;
    private $em;

    public function __construct(RequestStack $request,EntityManagerInterface $em){

        $this->request = $request;
        $this->em = $em;
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('default/index.html.twig', [
            'controller_name' => 'AppController',
        ]);
    }

    /**
     * Formulaire de vote en ligne : premier vote ou modification.
     * Les résultats y sont affichés si le membre a déjà voté,
     * ou si la question les rend visibles avant participation.
     * Une fois la question close, la page n'affiche plus que les résultats.
     */
    #[Route('/a/{a_token}', name: 'answer_form', methods: ['GET', 'POST'])]
    public function answerForm(Request $request, string $a_token): Response
    {
        /** @var Answer|null $answer */
        $answer = $this->em->getRepository(Answer::class)->findOneBy(['token' => $a_token]);
        if (!$answer) {
            return $this->render('default/error.html.twig', ['message' => self::INVALID_LINK_MESSAGE]);
        }
        $question = $answer->getQuestion();
        $hasAnswered = !$answer->getChoices()->isEmpty();
        $closed = $question->isClosed();
        $error = null;

        if ($request->isMethod('POST')) {
            if ($closed) {
                $error = 'Cette consultation est close : les réponses ne peuvent plus être enregistrées ni modifiées.';
            } elseif (!$this->isCsrfTokenValid('answer_form_'.$answer->getToken(), (string) $request->request->get('_token'))) {
                $error = 'Votre session a expiré, merci de réessayer.';
            } else {
                $submitted = array_map('intval', (array) $request->request->all('choices'));
                $choices = $question->getChoices()->filter(fn (Choice $c) => in_array($c->getId(), $submitted, true));

                if ($choices->isEmpty()) {
                    $error = 'Merci de sélectionner une réponse.';
                } elseif (!$question->isMultichoices() && count($choices) > 1) {
                    $error = 'Une seule réponse est possible pour cette question.';
                } else {
                    foreach ($answer->getChoices()->toArray() as $choice) {
                        $answer->removeChoice($choice);
                    }
                    foreach ($choices as $choice) {
                        $answer->addChoice($choice);
                    }
                    $this->em->flush();

                    $this->addFlash('success', $hasAnswered ? 'Votre réponse a bien été modifiée.' : 'Merci, votre réponse a bien été enregistrée.');

                    return $this->render('default/thank_you.html.twig', [
                        'contact' => $answer->getContact(),
                        'question' => $question,
                        'answer' => $answer,
                    ]);
                }
            }
        }

        return $this->render('default/answer_form.html.twig', [
            'answer' => $answer,
            'question' => $question,
            'contact' => $answer->getContact(),
            'has_answered' => $hasAnswered,
            'closed' => $closed,
            'show_results' => $closed || $hasAnswered || $question->isResultsVisibleBeforeVote(),
            'error' => $error,
        ], new Response(status: $error ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/a/{a_token}/{b64_choices_list}', name: 'answer_by_link')]
    public function reply(RequestContext $request,string $a_token, string $b64_choices_list): Response
    {
        /** @var \App\Entity\Answer $answer */
        $answer = $this->em->getRepository(Answer::class)->findOneBy(['token'=>$a_token]);
        if (!$answer){
            return $this->render('default/error.html.twig',['message' => self::INVALID_LINK_MESSAGE]);
        }

        //get contact
        $contact = $answer->getContact();
        //get question
        $question = $answer->getQuestion();

        // Question close : aucun enregistrement, on affiche l'état final
        if ($question->isClosed()) {
            return $this->redirectToRoute('answer_form', ['a_token' => $answer->getToken()]);
        }

        // Decoded payload must be a comma-separated list of ids ("12" or "12,15")
        $decoded = base64_decode($b64_choices_list, true);
        if ($decoded === false || !preg_match('/^\d+(,\d+)*$/', $decoded)) {
            return $this->render('default/error.html.twig',['message' => self::INVALID_LINK_MESSAGE]);
        }
        $choices_id = array_values(array_unique(array_map('intval', explode(',', $decoded))));

        // Every id must belong to this answer's question, and only one is allowed for single-choice questions
        $choices = $question->getChoices()->filter(fn (Choice $c) => in_array($c->getId(), $choices_id, true));
        if (count($choices) !== count($choices_id)
            || ($choices->isEmpty())
            || (!$question->isMultichoices() && count($choices) > 1)) {
            return $this->render('default/error.html.twig',['message' => self::INVALID_LINK_MESSAGE]);
        }

        //check for existing reply
        $alreadyAnswered = count($answer->getChoices()) >= 1;
        if (!$alreadyAnswered) {
            foreach($choices as $choice){
                $answer->addChoice($choice);
            }
            $this->em->persist($answer);
            $this->em->flush();
        }
        return $this->render('default/thank_you.html.twig',
            [   'contact'=>$contact,
                'question'=>$question,
                'answer' => $answer,
                'already_answered' => $alreadyAnswered,
            ]);
    }
}
