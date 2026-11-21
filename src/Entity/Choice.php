<?php

namespace App\Entity;

use App\Repository\ChoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChoiceRepository::class)]
class Choice
{
    /**
     * Ready-made themes: [label, background, text color, border color].
     * The first three mirror the chips of the home page email preview.
     */
    public const THEMES = [
        'mint'     => ['Menthe',   '#e3f6ef', '#2e7d63', '#d8eae3'],
        'sky'      => ['Ciel',     '#e6f3fc', '#2f6f99', '#dbe7ef'],
        'rose'     => ['Rose',     '#fdebef', '#b4455d', '#f0dfe3'],
        'lavender' => ['Lavande',  '#ece8fd', '#5546b3', '#e0dcf0'],
        'peach'    => ['Pêche',    '#fff0e8', '#a8542a', '#f2e4dc'],
        'neutral'  => ['Neutre',   '#f3f2f7', '#4a4760', '#e7e6eb'],
    ];

    public const DEFAULT_BACKGROUND = '#f3f2f7';
    public const DEFAULT_TEXT_COLOR = '#2f2b45';
    public const DEFAULT_BORDER_COLOR = '#e4e0f5';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'choices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Question $question = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[Assert\CssColor(formats: [Assert\CssColor::HEX_LONG, Assert\CssColor::HEX_SHORT])]
    private ?string $color = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[Assert\CssColor(formats: [Assert\CssColor::HEX_LONG, Assert\CssColor::HEX_SHORT])]
    private ?string $textColor = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[Assert\CssColor(formats: [Assert\CssColor::HEX_LONG, Assert\CssColor::HEX_SHORT])]
    private ?string $borderColor = null;

    /**
     * @var Collection<int, Answer>
     */
    #[ORM\ManyToMany(targetEntity: Answer::class, mappedBy: 'choices')]
    private Collection $answers;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    public function setTextColor(?string $textColor): static
    {
        $this->textColor = $textColor;

        return $this;
    }

    public function getBorderColor(): ?string
    {
        return $this->borderColor;
    }

    public function setBorderColor(?string $borderColor): static
    {
        $this->borderColor = $borderColor;

        return $this;
    }

    /**
     * Unmapped helper for the admin form: the theme picker only pre-fills the color fields client-side.
     */
    public function getTheme(): ?string
    {
        foreach (self::THEMES as $key => [, $bg, $text, $border]) {
            if (strcasecmp((string) $this->color, $bg) === 0
                && strcasecmp((string) $this->textColor, $text) === 0
                && strcasecmp((string) $this->borderColor, $border) === 0) {
                return $key;
            }
        }

        return null;
    }

    public function __toString(): string
    {
        return (string) $this->getLabel();
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
            $answer->addChoice($this);
        }

        return $this;
    }

    public function removeAnswer(Answer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            $answer->removeChoice($this);
        }

        return $this;
    }
}
