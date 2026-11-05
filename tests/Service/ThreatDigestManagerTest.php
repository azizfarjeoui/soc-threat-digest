<?php

namespace App\Tests\Service;

use App\Entity\FetchRun;
use App\Entity\ThreatReport;
use App\Repository\ThreatReportRepository;
use App\Service\Fetcher\ThreatSourceFetcherInterface;
use App\Service\ThreatDigestManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ThreatDigestManagerTest extends TestCase
{
    public function testFetchSourceDeduplicatesReports(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $reportRepo = $this->createMock(ThreatReportRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Prepare reports from fetcher
        $report1 = new ThreatReport();
        $report1->setSource('CISA_KEV');
        $report1->setExternalId('CVE-2026-0001');
        $report1->setTitle('Unique Threat');

        $report2 = new ThreatReport();
        $report2->setSource('CISA_KEV');
        $report2->setExternalId('CVE-2026-0002');
        $report2->setTitle('Duplicate Threat');

        $mockFetcher = $this->createMock(ThreatSourceFetcherInterface::class);
        $mockFetcher->method('getSourceCode')->willReturn('CISA_KEV');
        $mockFetcher->method('fetch')->willReturn([$report1, $report2]);

        // Mock deduplication repository check
        // CVE-2026-0001: Unique (exists = false)
        // CVE-2026-0002: Duplicate (exists = true)
        $reportRepo->method('exists')
            ->will($this->returnValueMap([
                ['CISA_KEV', 'CVE-2026-0001', false],
                ['CISA_KEV', 'CVE-2026-0002', true],
            ]));

        $entityManager->method('contains')->willReturn(true);

        // Expect ONLY the unique report to be persisted
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($report1);

        $manager = new ThreatDigestManager(
            [$mockFetcher],
            $entityManager,
            $reportRepo,
            $logger
        );

        $run = new FetchRun();
        $run->setNewReportsCount(0);

        $since = new \DateTime();
        $manager->fetchSource($run, 'CISA_KEV', $since);

        // Assert count updates correctly
        $this->assertEquals(1, $run->getNewReportsCount());
        
        // Assert processed source is logged
        $this->assertContains('CISA_KEV', $run->getProcessedSources());
    }
}
