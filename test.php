<?php
declare(strict_types=1);

use App\Exception\UnderflowException;
use App\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

require 'vendor/autoload.php';



$q = new OrderedObjectLibrary(
    borrowOrder: OrderedObjectLibrary::BORROW_TOP_FIRST,
    //initialState: [
    //    new Symfony\Component\String\ByteString('top'),
    //    new Symfony\Component\String\ByteString('1 from top'),
    //    new Symfony\Component\String\ByteString('2 from top'),
    //    new Symfony\Component\String\ByteString('3 from top'),
    //    new Symfony\Component\String\ByteString('bottom'),
    //],
    newElementFactory: fn() => new \Symfony\Component\String\ByteString('foo ' . \mt_rand())
);

dump($first = $q->borrow());
dump($second = $q->borrow());
dump($third = $q->borrow());
//dump($fourth = $q->borrow());
//dump($fifth = $q->borrow());
dump('--');
$q->return($third);
$q->return($first);
dump($q->borrow());
dump($q->borrow());
dump($q->borrow());
dump($q->borrow());
dump($q->borrow());
