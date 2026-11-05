<?php

namespace App\Service;

use App\Entity\ThreatReport;

class ThreatSourceCatalog
{
    /**
     * Built-in sources that have a PHP fetcher. Admin can add/remove these from the live catalog.
     *
     * @return array<string, array{name: string, apiUrl: string}>
     */
    public static function defaults(): array
    {
        return [
            ThreatReport::SOURCE_CISA_KEV => [
                'name' => 'CISA Known Exploited Vulnerabilities',
                'apiUrl' => 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json',
            ],
            ThreatReport::SOURCE_NVD => [
                'name' => 'NVD (CVEs)',
                'apiUrl' => 'https://services.nvd.nist.gov/rest/json/cves/2.0',
            ],
            ThreatReport::SOURCE_MALWARE_BAZAAR => [
                'name' => 'MalwareBazaar',
                'apiUrl' => 'https://mb-api.abuse.ch/api/v1/',
            ],
            ThreatReport::SOURCE_OTX => [
                'name' => 'AlienVault OTX',
                'apiUrl' => 'https://otx.alienvault.com/api/v1/pulses/subscribed',
            ],
        ];
    }

    public static function defaultUrl(string $code, string $fallback): string
    {
        return self::defaults()[$code]['apiUrl'] ?? $fallback;
    }
}
