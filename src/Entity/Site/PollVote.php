<?php

namespace App\Entity\Site;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'poll_votes')]
#[ORM\UniqueConstraint(name: 'uniq_poll_voter', columns: ['poll_id', 'voter_key'])]
class PollVote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(name: 'poll_id', nullable: false, onDelete: 'CASCADE')]
    private Poll $poll;

    #[ORM\ManyToOne(targetEntity: PollOption::class)]
    #[ORM\JoinColumn(name: 'option_id', nullable: false, onDelete: 'CASCADE')]
    private PollOption $option;

    #[ORM\Column(name: 'voter_key', length: 64)]
    private string $voterKey;

    #[ORM\Column(name: 'voted_at')]
    private \DateTimeImmutable $votedAt;

    public function __construct(Poll $poll, PollOption $option, string $voterKey)
    {
        $this->poll = $poll;
        $this->option = $option;
        $this->voterKey = $voterKey;
        $this->votedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): Poll
    {
        return $this->poll;
    }

    public function getOption(): PollOption
    {
        return $this->option;
    }

    public function getVoterKey(): string
    {
        return $this->voterKey;
    }

    public function getVotedAt(): \DateTimeImmutable
    {
        return $this->votedAt;
    }
}
