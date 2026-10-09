<?php

namespace App\Editorial;

use App\Entity\Site\Poll;
use App\Entity\Site\PollOption;
use Doctrine\ORM\EntityManagerInterface;

final class PollSyncService
{
    /**
     * @param list<string> $optionLabels
     */
    public function createPoll(
        string $question,
        array $optionLabels,
        bool $isOpen,
        bool $showResultsBeforeVote,
        ?\DateTimeImmutable $closesAt,
        EntityManagerInterface $em,
    ): Poll {
        $poll = new Poll($question);
        $poll->setIsOpen($isOpen)
            ->setShowResultsBeforeVote($showResultsBeforeVote)
            ->setClosesAt($closesAt);

        $sort = 0;
        foreach ($optionLabels as $label) {
            $label = trim($label);
            if ($label === '') {
                continue;
            }
            $poll->addOption(new PollOption($label, $sort++));
        }

        if ($poll->getOptions()->count() < 2) {
            throw new \InvalidArgumentException('La encuesta necesita al menos 2 opciones.');
        }

        $em->persist($poll);

        return $poll;
    }

  /**
     * @param list<string> $optionLabels
     */
    public function syncPoll(
        Poll $poll,
        string $question,
        array $optionLabels,
        bool $isOpen,
        bool $showResultsBeforeVote,
        ?\DateTimeImmutable $closesAt,
        EntityManagerInterface $em,
    ): void {
        $poll->setQuestion($question)
            ->setIsOpen($isOpen)
            ->setShowResultsBeforeVote($showResultsBeforeVote)
            ->setClosesAt($closesAt);

        $labels = [];
        foreach ($optionLabels as $label) {
            $label = trim($label);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        if (count($labels) < 2) {
            throw new \InvalidArgumentException('La encuesta necesita al menos 2 opciones.');
        }

        $existing = $poll->getOptions()->toArray();
        foreach ($existing as $index => $option) {
            if (isset($labels[$index])) {
                $option->setLabel($labels[$index])->setSortOrder($index);
            }
        }

        if (count($labels) > count($existing)) {
            for ($i = count($existing); $i < count($labels); ++$i) {
                $poll->addOption(new PollOption($labels[$i], $i));
            }
        }

        if (count($labels) < count($existing)) {
            $toRemove = array_slice($existing, count($labels));
            foreach ($toRemove as $option) {
                if ($option->getVoteCount() > 0) {
                    throw new \InvalidArgumentException('No se pueden eliminar opciones que ya tienen votos.');
                }
                $poll->getOptions()->removeElement($option);
                $em->remove($option);
            }
        }
    }

    /** @return list<string> */
    public function labelsFromRequest(array $rawOptions): array
    {
        if (isset($rawOptions[0]) || array_is_list($rawOptions)) {
            return array_map('strval', $rawOptions);
        }

        return array_values($rawOptions);
    }
}
