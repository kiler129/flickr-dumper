<?php
declare(strict_types=1);

namespace App\Struct\View;

readonly final class BreadcrumbDto
{
    public function __construct(
        public string $title,
        public string|null $url = null,
    )
    {
    }
}
