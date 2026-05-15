<?php

namespace App\Application\Core\Queries;

final class GetSettingsQuery
{
    /** @param string[] $keys  Empty = all keys caller expects */
    public function __construct(
        public readonly array $keys = [],
    ) {}
}
