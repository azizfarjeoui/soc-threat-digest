<?php

namespace App\MessageHandler;

use App\Message\FetchThreatSourceMessage;
use App\Message\TriggerScheduledFetchMessage;
use App\Service\ThreatDigestManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class TriggerScheduledFetchHandler
{
    private ThreatDigestManager $manager;
    private MessageBusInterface $messageBus;

    public function __construct(ThreatDigestManager $manager, MessageBusInterface $messageBus)
    {
        $this->manager = $manager;
        $this->messageBus = $messageBus;
    }

    public function __invoke(TriggerScheduledFetchMessage $message): void
    {
        // 1. Start the FetchRun tracker
        $run = $this->manager->startFetchRun();

        // 2. Dispatch a separate async message for each registered source
        $sources = $this->manager->getEnabledSourceCodes();
        foreach ($sources as $source) {
            $this->messageBus->dispatch(new FetchThreatSourceMessage($run->getId(), $source));
        }
    }
}
