<?php
declare(strict_types=1);

namespace App\Filesystem;

class StorageProvider
{
    public function __construct(private string $storageRoot)
    {}


}
