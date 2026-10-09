<?php

namespace App\Editorial;

use App\Entity\Site\Poll;
use App\Entity\Site\PollOption;
use App\Entity\Site\PollVote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PollVoteService
{
    private const COOKIE_NAME = 'mymedia_poll_voter';

    public function resolveVoterKey(Request $request): string
    {
        $cookie = $request->cookies->get(self::COOKIE_NAME);
        if (is_string($cookie) && strlen($cookie) === 64) {
            return $cookie;
        }

        return bin2hex(random_bytes(32));
    }

    public function attachVoterCookie(Response $response, string $voterKey): void
    {
        $response->headers->setCookie(Cookie::create(self::COOKIE_NAME)
            ->withValue($voterKey)
            ->withExpires(new \DateTimeImmutable('+2 years'))
            ->withPath('/')
            ->withSecure(false)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));
    }

    public function hasVoted(Poll $poll, string $voterKey, EntityManagerInterface $em): bool
    {
        $pollId = $poll->getId();
        if ($pollId === null) {
            return false;
        }

        return $em->getRepository(PollVote::class)->count([
            'poll' => $poll,
            'voterKey' => $voterKey,
        ]) > 0;
    }

    public function castVote(Poll $poll, int $optionId, string $voterKey, EntityManagerInterface $em): void
    {
        if (!$poll->isOpen()) {
            throw new \InvalidArgumentException('La encuesta está cerrada.');
        }

        if ($this->hasVoted($poll, $voterKey, $em)) {
            throw new \InvalidArgumentException('Ya has votado en esta encuesta.');
        }

        $option = null;
        foreach ($poll->getOptions() as $pollOption) {
            if ($pollOption->getId() === $optionId) {
                $option = $pollOption;
                break;
            }
        }

        if ($option === null) {
            throw new \InvalidArgumentException('Opción no válida.');
        }

        $vote = new PollVote($poll, $option, $voterKey);
        $option->incrementVoteCount();
        $em->persist($vote);
        $em->flush();
    }
}
