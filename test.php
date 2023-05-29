<?php
declare(strict_types=1);

exit;

//require 'vendor/autoload.php';

function foo(): string
{
    return 'x';
}

//$x = '';
$y = [];
$i = 1_000_000;
while (--$i >= 0) {
    //$x .= foo();
    $y[] = foo();
}

//echo strlen($x) . " bytes\n";
echo \count($y) . " elements\n";
