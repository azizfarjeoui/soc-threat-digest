<?php

namespace App\Service;

use App\Entity\ThreatSource;
use App\Repository\ThreatSourceRepository;

class ThreatSourceSettings
{
    /** @var array<string, ThreatSource|null> */
    private array $cache = [];

    public function __construct(private ThreatSourceRepository $sourceRepository)
    {
    }

    public function isEnabled(string $code): bool
    {
        $source = $this->getSource($code);

        return $source === null || (bool) $source->isEnabled();
    }

    public function getApiUrl(string $code, string $fallback): string
    {
        $source = $this->getSource($code);
        $url = $source?->getApiUrl();

        return !empty($url) ? $url : ThreatSourceCatalog::defaultUrl($code, $fallback);
    }

    public function getApiKey(string $code, ?string $envFallback = null): ?string
    {
        $source = $this->getSource($code);
        $fromDb = $this->normalizeKey($source?->getApiKey());
        if ($fromDb !== null) {
            return $fromDb;
        }

        return $this->normalizeKey($envFallback);
    }

    public function getSource(string $code): ?ThreatSource
    {
        if (!array_key_exists($code, $this->cache)) {
            $this->cache[$code] = $this->sourceRepository->findOneBy(['code' => $code]);
        }

        return $this->cache[$code];
    }

    private function normalizeKey(?string $key): ?string
    {
        if ($key === null || $key === '' || str_starts_with($key, 'your_')) {
            return null;
        }

        return $key;
    }
}
