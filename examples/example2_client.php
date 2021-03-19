<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use RFBP\Client;
use RFBP\Transport\DoctrineIpTransport;

$data = new ArrayObject([
    'client' => long2ip(random_int(ip2long("10.0.0.0"), ip2long("10.255.255.255"))),
    'number' => random_int(1, 9)
]);

$connection = DriverManager::getConnection(['url' => 'mysql://root:root@127.0.0.1:3306/rfbp?serverVersion=5.7']);
$transport = new DoctrineIpTransport($connection, uniqid('transport_', true));

$client = new Client($transport, $transport);

printf("Client %s : call for number %d\n", $data['client'], $data['number']);
$client->call($data);

$client->wait([
    ArrayObject::class => [function(ArrayObject $data) {
            if(is_null($data['number'])) {
                printf("Client %s : error in process\n", $data['client']);
            } else {
                printf("Client %s : result number %d\n", $data['client'], $data['number']);
            }
    }]
]);
