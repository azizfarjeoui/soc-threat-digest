<?php

namespace App\Service\Fetcher;

use App\Entity\ThreatReport;
use App\Service\ThreatSourceSettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class AlienVaultOtxFetcher implements ThreatSourceFetcherInterface
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private ?string $apiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        private ThreatSourceSettings $sourceSettings,
        ?string $otxApiKey = null,
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiKey = $otxApiKey;
    }

    public function getSourceCode(): string
    {
        return ThreatReport::SOURCE_OTX;
    }

    public function fetch(\DateTimeInterface $since): array
    {
        $this->logger->info('Starting AlienVault OTX fetch...');

        $apiKey = $this->sourceSettings->getApiKey($this->getSourceCode(), $this->apiKey);
        $url = $this->sourceSettings->getApiUrl($this->getSourceCode(), 'https://otx.alienvault.com/api/v1/pulses/subscribed');

        if (!$apiKey) {
            $this->logger->warning('AlienVault OTX API key is not configured. Skipping fetch.');
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'X-OTX-API-KEY' => $apiKey,
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to fetch OTX data. HTTP Status: ' . $response->getStatusCode());
            }

            $data = $response->toArray();
            $pulses = $data['results'] ?? [];
            $reports = [];

            foreach ($pulses as $pulse) {
                $modifiedStr = $pulse['modified'] ?? null;
                if (!$modifiedStr) {
                    continue;
                }

                $publishedAt = new \DateTime($modifiedStr);
                // Filter by $since
                if ($publishedAt < $since) {
                    continue;
                }

                $pulseId = $pulse['id'] ?? '';
                if (empty($pulseId)) {
                    continue;
                }

                $title = $pulse['name'] ?? 'Untitled OTX Pulse';
                $adversary = $pulse['adversary'] ?? null;
                $indicatorsCount = isset($pulse['indicators']) ? count($pulse['indicators']) : 0;
                
                $tags = isset($pulse['tags']) ? implode(', ', $pulse['tags']) : 'None';
                $description = sprintf(
                    "%s\n\nAuthor: %s\nAdversary/Threat Actor: %s\nIndicators: %d IOC(s)\nTags: %s",
                    $pulse['description'] ?? 'No description provided.',
                    $pulse['author_name'] ?? 'Unknown',
                    $adversary ?? 'None identified',
                    $indicatorsCount,
                    $tags
                );

                $severity = 'Medium';
                if ($indicatorsCount > 50 || !empty($adversary)) {
                    $severity = 'High';
                }

                $report = new ThreatReport();
                $report->setSource($this->getSourceCode());
                $report->setExternalId($pulseId);
                $report->setTitle($title);
                $report->setDescription($description);
                $report->setSeverity($severity);
                $report->setMalwareFamily($adversary);
                $report->setPublishedAt($publishedAt);
                $report->setSourceUrl('https://otx.alienvault.com/pulse/' . $pulseId);
                $report->setRawPayload($pulse);

                $reports[] = $report;
            }

            $this->logger->info(sprintf('Fetched %d reports from AlienVault OTX', count($reports)));
            return $reports;

        } catch (\Exception $e) {
            $this->logger->error('Error fetching AlienVault OTX: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}
