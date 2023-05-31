<?php
declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

require 'vendor/autoload.php';

dd(json_encode(\shell_exec('pngcheck /Volumes/Untitled/flsync/14373158_N05/2008/05/2503907469-medium_640.jpg')));
