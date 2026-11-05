<?php

namespace App\Service\Fetcher;

use App\Entity\ThreatReport;

interface ThreatSourceFetcherInterface
{
    /**
     * Fetch threat intelligence and return a list of normalized, unsaved ThreatReport entities.
     *
     * @param \DateTimeInterface $since Cutoff date for fetching.
     * @return ThreatReport[]
     */
    public function fetch(\DateTimeInterface $since): array;

    /**
     * Returns the threat source identifier (e.g. CISA_KEV, NVD, etc.)
     */
    public function getSourceCode(): string;
}
