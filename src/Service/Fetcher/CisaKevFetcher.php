<?php

namespace App\Service\Fetcher;

use App\Entity\ThreatReport;
use App\Service\ThreatSourceSettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class CisaKevFetcher implements ThreatSourceFetcherInterface
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        private ThreatSourceSettings $sourceSettings,
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    public function getSourceCode(): string
    {
        return ThreatReport::SOURCE_CISA_KEV;
    }

    public function fetch(\DateTimeInterface $since): array
    {
        $this->logger->info('Starting CISA KEV fetch...');
        try {
            $url = $this->sourceSettings->getApiUrl(
                $this->getSourceCode(),
                'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json'
            );
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 15,
            ]);
            
            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to fetch CISA KEV feed. HTTP Status: ' . $response->getStatusCode());
            }

            $data = $response->toArray();
            $vulnerabilities = $data['vulnerabilities'] ?? [];
            $reports = [];

            foreach ($vulnerabilities as $vuln) {
                $dateAddedStr = $vuln['dateAdded'] ?? null;
                if (!$dateAddedStr) {
                    continue;
                }

                $publishedAt = new \DateTime($dateAddedStr);
                // Filter by $since (truncate time to ensure same-day inclusion)
                $sinceDate = (clone $since)->setTime(0, 0, 0);
                if ($publishedAt < $sinceDate) {
                    continue;
                }

                $cveId = $vuln['cveID'] ?? '';
                if (empty($cveId)) {
                    continue;
                }

                $title = sprintf('%s - %s: %s', 
                    $vuln['vendorProject'] ?? 'Unknown Vendor',
                    $vuln['product'] ?? 'Unknown Product',
                    $vuln['vulnerabilityName'] ?? 'Unknown Vulnerability'
                );

                $description = sprintf("%s\n\nRequired Action: %s\nDue Date: %s\nKnown Ransomware Use: %s",
                    $vuln['shortDescription'] ?? '',
                    $vuln['requiredAction'] ?? 'None specified',
                    $vuln['dueDate'] ?? 'N/A',
                    $vuln['knownRansomwareCampaignUse'] ?? 'Unknown'
                );

                $report = new ThreatReport();
                $report->setSource($this->getSourceCode());
                $report->setExternalId($cveId);
                $report->setTitle($title);
                $report->setDescription($description);
                $report->setSeverity('High');
                $report->setPublishedAt($publishedAt);
                $report->setSourceUrl('https://nvd.nist.gov/vuln/detail/' . $cveId);
                $report->setRawPayload($vuln);

                $reports[] = $report;
            }

            $this->logger->info(sprintf('Fetched %d reports from CISA KEV matching the timeframe since %s', count($reports), $since->format('Y-m-d')));
            return $reports;

        } catch (\Exception $e) {
            $this->logger->error('Error fetching CISA KEV: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}
