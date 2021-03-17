<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Client;
use RFBP\IP;
use RFBP\Transport\DoctrineIpTransport;
use Doctrine\DBAL\DriverManager;

$data = new ArrayObject([
    'client' => long2ip(random_int(ip2long("10.0.0.0"), ip2long("10.255.255.255"))),
    'number' => random_int(1, 9)
]);

$connection = DriverManager::getConnection(['url' => 'mysql://root:root@127.0.0.1:3306/rfbp?serverVersion=5.7']);
$transport = new DoctrineIpTransport($connection, uniqid('transport_', true));

$client = new Client($transport, $transport);

printf("Client %s : call for number %d\n", $data['client'], $data['number']);
$client->call($data);

$client->wait(function(IP $ip) {
    $data = $ip->getData();
    if(is_null($data['number'])) {
        printf("Client %s : error in process\n", $data['client']);
    } else {
        printf("Client %s : result number %d\n", $data['client'], $data['number']);
    }
});
