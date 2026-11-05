<?php

namespace App\Tests\Service\Fetcher;

use App\Entity\ThreatReport;
use App\Service\Fetcher\CisaKevFetcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CisaKevFetcherTest extends TestCase
{
    public function testFetchNormalizesDataCorrectly(): void
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $jsonFeedData = [
            'title' => 'CISA KEV Catalog',
            'catalogVersion' => '2026.08.16',
            'dateReleased' => '2026-08-16',
            'vulnerabilities' => [
                [
                    'cveID' => 'CVE-2026-99999',
                    'vendorProject' => 'Mock Vendor',
                    'product' => 'Mock Product',
                    'vulnerabilityName' => 'Mock Vulnerability SQL injection',
                    'dateAdded' => '2026-08-15',
                    'shortDescription' => 'A mock SQL injection vulnerability in mock product.',
                    'requiredAction' => 'Apply vendor patch.',
                    'dueDate' => '2026-08-29',
                    'knownRansomwareCampaignUse' => 'Known',
                    'notes' => ''
                ]
            ]
        ];

        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn($jsonFeedData);

        $mockHttpClient->method('request')
            ->with('GET', $this->anything(), $this->anything())
            ->willReturn($mockResponse);

        $fetcher = new CisaKevFetcher($mockHttpClient, $mockLogger);

        // Fetch since 2026-08-14 (should include the vulnerability added on 2026-08-15)
        $since = new \DateTime('2026-08-14');
        $reports = $fetcher->fetch($since);

        $this->assertCount(1, $reports);
        
        $report = $reports[0];
        $this->assertInstanceOf(ThreatReport::class, $report);
        $this->assertEquals('CISA_KEV', $report->getSource());
        $this->assertEquals('CVE-2026-99999', $report->getExternalId());
        $this->assertEquals('Mock Vendor - Mock Product: Mock Vulnerability SQL injection', $report->getTitle());
        $this->assertStringContainsString('A mock SQL injection vulnerability', $report->getDescription());
        $this->assertStringContainsString('Required Action: Apply vendor patch.', $report->getDescription());
        $this->assertEquals('High', $report->getSeverity());
        $this->assertEquals('https://nvd.nist.gov/vuln/detail/CVE-2026-99999', $report->getSourceUrl());
        
        $raw = $report->getRawPayload();
        $this->assertEquals('CVE-2026-99999', $raw['cveID']);
    }

    public function testFetchFiltersOutOldRecords(): void
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $jsonFeedData = [
            'vulnerabilities' => [
                [
                    'cveID' => 'CVE-2026-99999',
                    'vendorProject' => 'Mock Vendor',
                    'product' => 'Mock Product',
                    'vulnerabilityName' => 'Mock Vulnerability',
                    'dateAdded' => '2026-08-10', // old
                ]
            ]
        ];

        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn($jsonFeedData);

        $mockHttpClient->method('request')->willReturn($mockResponse);

        $fetcher = new CisaKevFetcher($mockHttpClient, $mockLogger);

        // Since 2026-08-12 (the record is from 2026-08-10, so it should be filtered out!)
        $since = new \DateTime('2026-08-12');
        $reports = $fetcher->fetch($since);

        $this->assertCount(0, $reports);
    }
}
