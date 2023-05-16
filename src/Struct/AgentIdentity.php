<?php
declare(strict_types=1);

namespace App\Struct;

final class AgentIdentity
{
    public function __construct(
        public readonly string $userAgent,
        public readonly array $headers,
        public array $debugInfo = [],
    ) {
    }
}
