<?php

namespace App\Entity\Site;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'poll_options')]
#[ORM\Index(columns: ['poll_id', 'sort_order'], name: 'idx_poll_option_order')]
class PollOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: 'options')]
    #[ORM\JoinColumn(name: 'poll_id', nullable: false, onDelete: 'CASCADE')]
    private ?Poll $poll = null;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(name: 'sort_order', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'vote_count', options: ['default' => 0])]
    private int $voteCount = 0;

    public function __construct(string $label, int $sortOrder = 0)
    {
        $this->label = $label;
        $this->sortOrder = $sortOrder;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(?Poll $poll): self
    {
        $this->poll = $poll;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getVoteCount(): int
    {
        return $this->voteCount;
    }

    public function incrementVoteCount(): self
    {
        ++$this->voteCount;

        return $this;
    }
}
