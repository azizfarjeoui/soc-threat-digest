<?php

namespace App\Service;

use App\Entity\FetchRun;
use App\Entity\ThreatReport;
use App\Repository\ThreatReportRepository;
use App\Service\Fetcher\ThreatSourceFetcherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ThreatDigestManager
{
    /**
     * @var iterable<ThreatSourceFetcherInterface>
     */
    private iterable $fetchers;
    private EntityManagerInterface $entityManager;
    private ThreatReportRepository $threatReportRepository;
    private LoggerInterface $logger;

    public function __construct(
        iterable $fetchers,
        EntityManagerInterface $entityManager,
        ThreatReportRepository $threatReportRepository,
        LoggerInterface $logger,
        private ThreatSourceSettings $sourceSettings,
    ) {
        $this->fetchers = $fetchers;
        $this->entityManager = $entityManager;
        $this->threatReportRepository = $threatReportRepository;
        $this->logger = $logger;
    }

    public function startFetchRun(): FetchRun
    {
        $run = new FetchRun();
        $run->setStatus(FetchRun::STATUS_SUCCESS);
        $run->setRanAt(new \DateTime());
        $run->setNewReportsCount(0);
        $run->setErrors([]);

        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
    }

    public function fetchSource(FetchRun $run, string $sourceCode, \DateTimeInterface $since): void
    {
        // Re-merge or find the managed run if detached
        if (!$this->entityManager->contains($run)) {
            $run = $this->entityManager->find(FetchRun::class, $run->getId());
        }

        if (!$this->sourceSettings->isEnabled($sourceCode)) {
            $error = sprintf('Source %s is disabled in admin settings.', $sourceCode);
            $this->logger->warning($error);
            $this->addErrorToRun($run, $sourceCode, $error);
        } else {
            $fetcher = $this->getFetcherBySource($sourceCode);
            if (!$fetcher) {
                $error = sprintf('No fetcher found for source: %s', $sourceCode);
                $this->logger->error($error);
                $this->addErrorToRun($run, $sourceCode, $error);
            } else {
                try {
                    $reports = $fetcher->fetch($since);
                    $newCount = 0;

                    foreach ($reports as $report) {
                        if ($this->threatReportRepository->exists($report->getSource(), $report->getExternalId())) {
                            continue;
                        }

                        $run->addThreatReport($report);
                        $this->entityManager->persist($report);
                        $newCount++;
                    }

                    $run->setNewReportsCount($run->getNewReportsCount() + $newCount);
                    $this->logger->info(sprintf('Source %s import complete. Added %d new reports.', $sourceCode, $newCount));
                } catch (\Exception $e) {
                    $errorMsg = sprintf('Fetcher failed: %s', $e->getMessage());
                    $this->logger->error(sprintf('Error during fetch for source %s: %s', $sourceCode, $e->getMessage()), [
                        'exception' => $e
                    ]);
                    $this->addErrorToRun($run, $sourceCode, $errorMsg);
                }
            }
        }

        // Track that this source has been processed (success or failure)
        $processed = $run->getProcessedSources() ?? [];
        if (!in_array($sourceCode, $processed, true)) {
            $processed[] = $sourceCode;
            $run->setProcessedSources($processed);
        }

        $this->entityManager->flush();
    }

    public function completeFetchRun(FetchRun $run, ?array $expectedSources = null): void
    {
        if (!$this->entityManager->contains($run)) {
            $run = $this->entityManager->find(FetchRun::class, $run->getId());
        }

        $processed = $run->getProcessedSources() ?? [];
        $errors = $run->getErrors() ?? [];
        $totalSources = count($expectedSources ?? $this->getEnabledSourceCodes());

        if ($totalSources === 0) {
            $run->setStatus(FetchRun::STATUS_SUCCESS);
            $this->entityManager->flush();
            return;
        }

        if (count($processed) >= $totalSources) {
            if (!empty($errors)) {
                $failedSourcesCount = count($errors);
                if ($failedSourcesCount >= $totalSources) {
                    $run->setStatus(FetchRun::STATUS_FAILED);
                } else {
                    $run->setStatus(FetchRun::STATUS_PARTIAL_FAILURE);
                }
            } else {
                $run->setStatus(FetchRun::STATUS_SUCCESS);
            }
        } else {
            // Still in progress
            $run->setStatus('RUNNING');
        }

        $this->entityManager->flush();
    }

    public function getFetcherBySource(string $sourceCode): ?ThreatSourceFetcherInterface
    {
        foreach ($this->fetchers as $fetcher) {
            if ($fetcher->getSourceCode() === $sourceCode) {
                return $fetcher;
            }
        }
        return null;
    }

    public function getRegisteredSourceCodes(): array
    {
        $codes = [];
        foreach ($this->fetchers as $fetcher) {
            $codes[] = $fetcher->getSourceCode();
        }
        return $codes;
    }

    public function getEnabledSourceCodes(): array
    {
        $enabled = [];
        foreach ($this->getRegisteredSourceCodes() as $code) {
            if ($this->sourceSettings->isEnabled($code)) {
                $enabled[] = $code;
            }
        }

        return $enabled;
    }

    private function addErrorToRun(FetchRun $run, string $sourceCode, string $error): void
    {
        $errors = $run->getErrors() ?? [];
        $errors[$sourceCode] = $error;
        $run->setErrors($errors);
        
        $run->setStatus(FetchRun::STATUS_PARTIAL_FAILURE);
    }
}
