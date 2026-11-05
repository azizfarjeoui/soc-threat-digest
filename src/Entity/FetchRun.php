<?php

namespace App\Entity;

use App\Repository\FetchRunRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FetchRunRepository::class)]
#[ORM\Table(name: 'fetch_run')]
class FetchRun
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_PARTIAL_FAILURE = 'PARTIAL_FAILURE';
    public const STATUS_FAILED = 'FAILED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'ran_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $ranAt = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(name: 'new_reports_count')]
    private ?int $newReportsCount = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $errors = [];

    #[ORM\Column(name: 'processed_sources', type: Types::JSON, nullable: true)]
    private ?array $processedSources = [];


    #[ORM\OneToMany(mappedBy: 'fetchRun', targetEntity: ThreatReport::class, cascade: ['persist'])]
    private Collection $threatReports;

    public function __construct()
    {
        $this->ranAt = new \DateTime();
        $this->threatReports = new ArrayCollection();
        $this->status = self::STATUS_SUCCESS;
        $this->processedSources = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRanAt(): ?\DateTimeInterface
    {
        return $this->ranAt;
    }

    public function setRanAt(\DateTimeInterface $ranAt): static
    {
        $this->ranAt = $ranAt;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getNewReportsCount(): ?int
    {
        return $this->newReportsCount;
    }

    public function setNewReportsCount(int $newReportsCount): static
    {
        $this->newReportsCount = $newReportsCount;
        return $this;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function setErrors(?array $errors): static
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * @return Collection<int, ThreatReport>
     */
    public function getThreatReports(): Collection
    {
        return $this->threatReports;
    }

    public function addThreatReport(ThreatReport $threatReport): static
    {
        if (!$this->threatReports->contains($threatReport)) {
            $this->threatReports->add($threatReport);
            $threatReport->setFetchRun($this);
        }

        return $this;
    }

    public function removeThreatReport(ThreatReport $threatReport): static
    {
        if ($this->threatReports->removeElement($threatReport)) {
            // set the owning side to null (unless already changed)
            if ($threatReport->getFetchRun() === $this) {
                $threatReport->setFetchRun(null);
            }
        }

        return $this;
    }

    public function getProcessedSources(): ?array
    {
        return $this->processedSources;
    }

    public function setProcessedSources(?array $processedSources): static
    {
        $this->processedSources = $processedSources;
        return $this;
    }
}

