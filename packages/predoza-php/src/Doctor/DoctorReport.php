<?php

declare(strict_types=1);

namespace Predoza\Doctor;

class DoctorReport
{
    /**
     * @param CheckResult[] $results
     */
    public function __construct(public readonly array $results)
    {
    }

    public function passed(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === CheckStatus::Failed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[] one line per check, for a terminal
     */
    public function lines(): array
    {
        $width = max(array_map(static fn (CheckResult $result): int => strlen($result->name), $this->results) ?: [0]);

        return array_map(
            static fn (CheckResult $result): string => sprintf(' %s %s  %s', $result->status->symbol(), str_pad($result->name, $width), $result->detail),
            $this->results,
        );
    }

    public function summary(): string
    {
        $count = fn (CheckStatus $status): int => count(array_filter($this->results, static fn (CheckResult $result): bool => $result->status === $status));

        return sprintf('%d checks · %d passed · %d failed · %d warnings', count($this->results), $count(CheckStatus::Ok), $count(CheckStatus::Failed), $count(CheckStatus::Warning));
    }
}
