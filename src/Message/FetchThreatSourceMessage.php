<?php

namespace App\Message;

class FetchThreatSourceMessage
{
    private int $fetchRunId;
    private string $sourceCode;
    private int $days;

    public function __construct(int $fetchRunId, string $sourceCode, int $days = 7)
    {
        $this->fetchRunId = $fetchRunId;
        $this->sourceCode = $sourceCode;
        $this->days = $days;
    }

    public function getFetchRunId(): int
    {
        return $this->fetchRunId;
    }

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    public function getDays(): int
    {
        return $this->days;
    }
}
