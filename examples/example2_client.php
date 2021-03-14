<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Client;
use RFBP\IP;
use RFBP\Transport\DoctrineIpTransport;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

$data = new ArrayObject([
    'client' => long2ip(random_int(ip2long("10.0.0.0"), ip2long("10.255.255.255"))),
    'number' => random_int(1, 9)
]);

//$sender = new AmqpTransport(Connection::fromDsn('amqp://guest:guest@rabbitmq:5672/%2f/supervisor-messages'));
//$receiver = new AmqpTransport(Connection::fromDsn('amqp://guest:guest@rabbitmq:5672/%2f/client-messages'));

$configuration = new \Doctrine\DBAL\Configuration();
//$configuration->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger());
$connection = DriverManager::getConnection(['url' => 'mysql://root:root@127.0.0.1:3306/rfbp?serverVersion=5.7'], $configuration);
$serializer = new PhpSerializer();

//$sender = new DoctrineTransport(new Connection(Connection::buildConfiguration('doctrine://default?table_name=supervisor_messages'), $connection), $serializer);
//$receiver = new DoctrineTransport(new Connection(Connection::buildConfiguration('doctrine://default?table_name=client_messages'), $connection), $serializer);

$transport = new DoctrineIpTransport($connection, uniqid('transport_', true));

$client = new Client($transport, $transport);

printf("Client %s : call for number %d\n", $data['client'], $data['number']);
$client->call($data);

$client->wait(function(IP $ip) {
    $data = $ip->getData();
    printf("Client %s : result number %d\n", $data['client'], $data['number']);
});
