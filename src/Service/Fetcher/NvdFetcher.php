<?php

namespace App\Service\Fetcher;

use App\Entity\ThreatReport;
use App\Service\ThreatSourceSettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class NvdFetcher implements ThreatSourceFetcherInterface
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private ?string $apiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        private ThreatSourceSettings $sourceSettings,
        ?string $nvdApiKey = null,
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiKey = $nvdApiKey;
    }

    public function getSourceCode(): string
    {
        return ThreatReport::SOURCE_NVD;
    }

    public function fetch(\DateTimeInterface $since): array
    {
        $this->logger->info('Starting NVD fetch...');
        
        $now = new \DateTime();
        $pubStartDate = $since->format('Y-m-d\TH:i:s.000');
        $pubEndDate = $now->format('Y-m-d\TH:i:s.000');

        $apiKey = $this->sourceSettings->getApiKey($this->getSourceCode(), $this->apiKey);
        $url = $this->sourceSettings->getApiUrl($this->getSourceCode(), 'https://services.nvd.nist.gov/rest/json/cves/2.0');

        $headers = [];
        if ($apiKey) {
            $headers['apiKey'] = $apiKey;
            $this->logger->info('Using NVD API key for request.');
        } else {
            $this->logger->info('No NVD API key configured, using public rate limits.');
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => [
                    'pubStartDate' => $pubStartDate,
                    'pubEndDate' => $pubEndDate,
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to fetch NVD data. HTTP Status: ' . $response->getStatusCode());
            }

            $data = $response->toArray();
            $vulnerabilities = $data['vulnerabilities'] ?? [];
            $reports = [];

            foreach ($vulnerabilities as $item) {
                $cve = $item['cve'] ?? null;
                if (!$cve) {
                    continue;
                }

                $cveId = $cve['id'] ?? '';
                if (empty($cveId)) {
                    continue;
                }

                // Extract description (prefer English)
                $description = '';
                foreach ($cve['descriptions'] ?? [] as $desc) {
                    if (($desc['lang'] ?? '') === 'en') {
                        $description = $desc['value'] ?? '';
                        break;
                    }
                }

                if (empty($description) && !empty($cve['descriptions'])) {
                    $description = $cve['descriptions'][0]['value'] ?? '';
                }

                // Title: short snippet
                $shortDesc = strlen($description) > 80 ? substr($description, 0, 77) . '...' : $description;
                $title = sprintf('%s - %s', $cveId, $shortDesc);

                $publishedStr = $cve['published'] ?? null;
                $publishedAt = $publishedStr ? new \DateTime($publishedStr) : new \DateTime();

                $severity = $this->getSeverity($cve);

                $report = new ThreatReport();
                $report->setSource($this->getSourceCode());
                $report->setExternalId($cveId);
                $report->setTitle($title);
                $report->setDescription($description);
                $report->setSeverity($severity);
                $report->setPublishedAt($publishedAt);
                $report->setSourceUrl('https://nvd.nist.gov/vuln/detail/' . $cveId);
                $report->setRawPayload($cve);

                $reports[] = $report;
            }

            $this->logger->info(sprintf('Fetched %d reports from NVD', count($reports)));
            return $reports;

        } catch (\Exception $e) {
            $this->logger->error('Error fetching NVD: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    private function getSeverity(array $cve): ?string
    {
        $metrics = $cve['metrics'] ?? [];
        
        // Try CVSS v3.1
        if (!empty($metrics['cvssMetricV31'])) {
            $metric = $metrics['cvssMetricV31'][0]['cvssData'] ?? [];
            if (isset($metric['baseScore'])) {
                return sprintf('%s (CVSS %s)', $metric['baseSeverity'] ?? 'UNKNOWN', $metric['baseScore']);
            }
        }
        
        // Try CVSS v3.0
        if (!empty($metrics['cvssMetricV30'])) {
            $metric = $metrics['cvssMetricV30'][0]['cvssData'] ?? [];
            if (isset($metric['baseScore'])) {
                return sprintf('%s (CVSS %s)', $metric['baseSeverity'] ?? 'UNKNOWN', $metric['baseScore']);
            }
        }

        // Try CVSS v2
        if (!empty($metrics['cvssMetricV2'])) {
            $metric = $metrics['cvssMetricV2'][0]['cvssData'] ?? [];
            if (isset($metric['baseScore'])) {
                return sprintf('%s (CVSS %s)', $metric['baseSeverity'] ?? 'UNKNOWN', $metric['baseScore']);
            }
        }

        return 'Medium';
    }
}
