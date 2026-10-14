<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Library\Application\Ports\Clock;
use DateTimeImmutable;

final class FakeClock implements Clock
{
    public function __construct(private DateTimeImmutable $instant)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }

    public function set(DateTimeImmutable $instant): void
    {
        $this->instant = $instant;
    }
}
