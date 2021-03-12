<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Client;
use RFBP\IP;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;

$data = new ArrayObject([
    'client' => long2ip(random_int(ip2long("10.0.0.0"), ip2long("10.255.255.255"))),
    'number' => 1
]);

$sender = new RedisTransport(Connection::fromDsn('redis://localhost:6379/supervisor-messages'));
$receiver = new RedisTransport(Connection::fromDsn('redis://localhost:6379/client-messages'));
$client = new Client($sender, $receiver);

printf("Client %s : call for number %d\n", $data['client'], $data['number']);
$client->call($data);

$client->wait(function(IP $ip) {
    $data = $ip->getData();
    printf("Client %s : result number %d\n", $data['client'], $data['number']);
});
