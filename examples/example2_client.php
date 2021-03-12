<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Client;

$client1 = new Client($producer);
$client2 = new Client($producer);
$client3 = new Client($producer);
$client4 = new Client($producer);
$client5 = new Client($producer);
$client6 = new Client($producer);
$client7 = new Client($producer);
$client8 = new Client($producer);
$client9 = new Client($producer);

$client1->call(['client' => '1', 'number' => 1]);
$client2->call(['client' => '2', 'number' => 2]);
$client3->call(['client' => '3', 'number' => 3]);
$client4->call(['client' => '4', 'number' => 4]);
$client5->call(['client' => '5', 'number' => 5]);
$client6->call(['client' => '6', 'number' => 6]);
$client7->call(['client' => '7', 'number' => 7]);
$client8->call(['client' => '8', 'number' => 8]);
$client9->call(['client' => '9', 'number' => 9]);

