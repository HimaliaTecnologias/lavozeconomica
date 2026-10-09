<?php

namespace App\Entity\Site;

use App\Repository\Site\PollRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PollRepository::class)]
#[ORM\Table(name: 'polls')]
class Poll
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    private string $question;

    #[ORM\Column(name: 'is_open', options: ['default' => true])]
    private bool $isOpen = true;

    #[ORM\Column(name: 'show_results_before_vote', options: ['default' => false])]
    private bool $showResultsBeforeVote = false;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'closes_at', nullable: true)]
    private ?\DateTimeImmutable $closesAt = null;

    /** @var Collection<int, PollOption> */
    #[ORM\OneToMany(mappedBy: 'poll', targetEntity: PollOption::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $options;

    /** @var Collection<int, PollVote> */
    #[ORM\OneToMany(mappedBy: 'poll', targetEntity: PollVote::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $votes;

    public function __construct(string $question)
    {
        $this->question = $question;
        $this->createdAt = new \DateTimeImmutable();
        $this->options = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function isOpen(): bool
    {
        if (!$this->isOpen) {
            return false;
        }

        if ($this->closesAt !== null && $this->closesAt < new \DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    public function getIsOpenFlag(): bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): self
    {
        $this->isOpen = $isOpen;

        return $this;
    }

    public function isShowResultsBeforeVote(): bool
    {
        return $this->showResultsBeforeVote;
    }

    public function setShowResultsBeforeVote(bool $showResultsBeforeVote): self
    {
        $this->showResultsBeforeVote = $showResultsBeforeVote;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getClosesAt(): ?\DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(?\DateTimeImmutable $closesAt): self
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    /** @return Collection<int, PollOption> */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(PollOption $option): self
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setPoll($this);
        }

        return $this;
    }

    public function clearOptions(): self
    {
        foreach ($this->options as $option) {
            $option->setPoll(null);
        }
        $this->options->clear();

        return $this;
    }

    public function getTotalVotes(): int
    {
        $total = 0;
        foreach ($this->options as $option) {
            $total += $option->getVoteCount();
        }

        return $total;
    }
}
