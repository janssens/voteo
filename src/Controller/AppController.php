<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Choice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RequestContext;

final class AppController extends AbstractController
{
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

    #[Route('/a/{a_token}/{b64_choices_list}', name: 'answer_by_link')]
    public function reply(RequestContext $request,string $a_token, string $b64_choices_list): Response
    {
        /** @var \App\Entity\Answer $answer */
        $answer = $this->em->getRepository(Answer::class)->findOneBy(['token'=>$a_token]);
        if (!$answer){
            return $this->render('default/error.html.twig',['message'=>'L&rsquo;url que vous utilisez ne correspond pas à une réponse possible']);
        }

        $choices_id = explode(',',base64_decode($b64_choices_list));
        /** @var \App\Entity\Answer $answer */
        $choices = $this->em->getRepository(Choice::class)->findBy(['id'=>$choices_id]);
        if (count($choices)<1){
            return $this->render('default/error.html.twig',['message'=>'L&rsquo;url que vous utilisez ne correspond pas à une réponse possible']);
        }
        //get contact
        $contact = $answer->getContact();
        //get question
        $question = $answer->getQuestion();
        
        //check for existing reply
        if (count($answer->getChoices()) >= 1) {
            $this->addFlash('notice', 'Vous avez déjà répondu à cette question.');
        }else{
            foreach($choices as $choice){
                $answer->addChoice($choice);
            }
            $this->em->persist($answer);
            $this->em->flush();
        }
        return $this->render('default/thank_you.html.twig',
            [   'contact'=>$contact,
                'question'=>$question,
                'answer' => $answer
            ]);
    }
}
