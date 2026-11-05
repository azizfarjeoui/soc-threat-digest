<?php

namespace App\MessageHandler;

use App\Message\FetchThreatSourceMessage;
use App\Repository\FetchRunRepository;
use App\Service\ThreatDigestManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FetchThreatSourceHandler
{
    private ThreatDigestManager $manager;
    private FetchRunRepository $runRepo;

    public function __construct(ThreatDigestManager $manager, FetchRunRepository $runRepo)
    {
        $this->manager = $manager;
        $this->runRepo = $runRepo;
    }

    public function __invoke(FetchThreatSourceMessage $message): void
    {
        $run = $this->runRepo->find($message->getFetchRunId());
        if (!$run) {
            return;
        }

        $since = new \DateTime(sprintf('-%d days', $message->getDays()));
        
        // Run fetch and deduplication for this single source
        $this->manager->fetchSource($run, $message->getSourceCode(), $since);

        // Update run completion status
        $this->manager->completeFetchRun($run);
    }
}
