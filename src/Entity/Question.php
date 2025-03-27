<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner ;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $intro = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $outro = null;

    #[ORM\Column]
    private bool $is_multichoices = false;

    /**
     * @var Collection<int, Choice>
     */
    #[ORM\OneToMany(targetEntity: Choice::class, mappedBy: 'question', orphanRemoval: true, cascade: ['persist'])]
    private Collection $choices;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    /**
     * @var Collection<int, ContactList>
     */
    #[ORM\ManyToMany(targetEntity: ContactList::class, inversedBy: 'questions')]
    private Collection $lists;

    #[ORM\Column]
    private ?bool $sent = false;

    /**
     * @var Collection<int, Answer>
     */
    #[ORM\OneToMany(targetEntity: Answer::class, mappedBy: 'question', orphanRemoval: true)]
    private Collection $answers;

    public function __construct()
    {
        $this->choices = new ArrayCollection();
        $this->lists = new ArrayCollection();
        $this->sent = false;
        $this->answers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getIntro(): ?string
    {
        return $this->intro;
    }

    public function setIntro(?string $intro): static
    {
        $this->intro = $intro;

        return $this;
    }

    public function getOutro(): ?string
    {
        return $this->outro;
    }

    public function setOutro(?string $outro): static
    {
        $this->outro = $outro;

        return $this;
    }

    public function isMultichoices(): ?bool
    {
        return $this->is_multichoices;
    }

    public function setIsMultichoices(bool $is_multichoices): static
    {
        $this->is_multichoices = $is_multichoices;

        return $this;
    }

    /**
     * @return Collection<int, Choice>
     */
    public function getChoices(): Collection
    {
        return $this->choices;
    }

    public function addChoice(Choice $choice): static
    {
        if (!$this->choices->contains($choice)) {
            $this->choices->add($choice);
            $choice->setQuestion($this);
        }

        return $this;
    }

    public function removeChoice(Choice $choice): static
    {
        if ($this->choices->removeElement($choice)) {
            // set the owning side to null (unless already changed)
            if ($choice->getQuestion() === $this) {
                $choice->setQuestion(null);
            }
        }

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @return Collection<int, ContactList>
     */
    public function getLists(): Collection
    {
        return $this->lists;
    }

    public function addList(ContactList $list): static
    {
        if (!$this->lists->contains($list)) {
            $this->lists->add($list);
        }

        return $this;
    }

    public function removeList(ContactList $list): static
    {
        $this->lists->removeElement($list);

        return $this;
    }

    public function isSent(): ?bool
    {
        return $this->sent;
    }

    public function setSent(bool $sent): static
    {
        $this->sent = $sent;

        return $this;
    }

    /**
     * @return Collection<int, Answer>
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(Answer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setQuestion($this);
        }

        return $this;
    }

    public function removeAnswer(Answer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            // set the owning side to null (unless already changed)
            if ($answer->getQuestion() === $this) {
                $answer->setQuestion(null);
            }
        }

        return $this;
    }

    public function getReponseRate(): float
    {
        if (!$this->isSent()){
            return 0;
        }
        $total_of_possible_answers = count($this->getAnswers());
        $answers_with_choices = 0;
        foreach( $this->getAnswers() as $answer){
            if (count($answer->getChoices()) > 0)
            {
                $answers_with_choices++;
            }
        }
        return $answers_with_choices / $total_of_possible_answers;
    }

}
