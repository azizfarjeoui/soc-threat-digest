<?php

namespace App\Entity;

use App\Repository\ThreatReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThreatReportRepository::class)]
#[ORM\Table(name: 'threat_report')]
#[ORM\UniqueConstraint(name: 'uniq_source_external_id', columns: ['source', 'external_id'])]
class ThreatReport
{
    public const SOURCE_CISA_KEV = 'CISA_KEV';
    public const SOURCE_NVD = 'NVD';
    public const SOURCE_MALWARE_BAZAAR = 'MALWARE_BAZAAR';
    public const SOURCE_OTX = 'OTX';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $source = null;

    #[ORM\Column(name: 'external_id', length: 255)]
    private ?string $externalId = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $severity = null;

    #[ORM\Column(name: 'malware_family', length: 255, nullable: true)]
    private ?string $malwareFamily = null;

    #[ORM\Column(name: 'published_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\Column(name: 'fetched_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fetchedAt = null;

    #[ORM\Column(name: 'source_url', length: 1000, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(name: 'raw_payload', type: Types::JSON)]
    private array $rawPayload = [];

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: FetchRun::class, inversedBy: 'threatReports')]
    #[ORM\JoinColumn(name: 'fetch_run_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?FetchRun $fetchRun = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->fetchedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(?string $severity): static
    {
        $this->severity = $severity;
        return $this;
    }

    public function getMalwareFamily(): ?string
    {
        return $this->malwareFamily;
    }

    public function setMalwareFamily(?string $malwareFamily): static
    {
        $this->malwareFamily = $malwareFamily;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeInterface $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getFetchedAt(): ?\DateTimeInterface
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(\DateTimeInterface $fetchedAt): static
    {
        $this->fetchedAt = $fetchedAt;
        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;
        return $this;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function setRawPayload(array $rawPayload): static
    {
        $this->rawPayload = $rawPayload;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getFetchRun(): ?FetchRun
    {
        return $this->fetchRun;
    }

    public function setFetchRun(?FetchRun $fetchRun): static
    {
        $this->fetchRun = $fetchRun;
        return $this;
    }
}
