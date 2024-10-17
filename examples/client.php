<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Flow\Examples\Transport\Client;
use Flow\Examples\Transport\DoctrineIpTransport;

$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path' => __DIR__ . '/flow.sqlite',
]);
$transport = new DoctrineIpTransport($connection, uniqid('transport_', true));

$client = new Client($transport, $transport);

$ip = long2ip(random_int(ip2long('10.0.0.0'), ip2long('10.255.255.255')));
for ($i = 0; $i < 3; $i++) {
    $data = new ArrayObject([
        'client' => $ip,
        'id' => $i,
        'number' => random_int(1, 9),
    ]);
    $delay = random_int(1, 10); // simulating 1 and 10 second delay

    printf("Client %s #%d: call for number %d with delay %d seconds\n", $data['client'], $data['id'], $data['number'], $delay);
    $client->call($data, $delay * 1000);
}

$client->wait([
    ArrayObject::class => [static function (ArrayObject $data) {
        if (null === $data['number']) {
            printf("Client %s #%d: error in process\n", $data['client'], $data['id']);
        } else {
            printf("Client %s #%d: result number %d\n", $data['client'], $data['id'], $data['number']);
        }
    }],
]);
