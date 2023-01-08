<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Flow\Examples\Transport\DoctrineIpTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Worker;

class Client
{
    public function __construct(
        private SenderInterface $sender,
        private ReceiverInterface $receiver
    ) {
    }

    /**
     * @param ?int $delay The delay in milliseconds
     */
    public function call(object $data, ?int $delay = null): void
    {
        $ip = Envelope::wrap($data, $delay ? [new DelayStamp($delay)] : []);
        $this->sender->send($ip);
    }

    /**
     * @param HandlerDescriptor[][]|callable[][] $handlers
     */
    public function wait(array $handlers): void
    {
        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator($handlers)),
        ]);
        $worker = new Worker(['transport' => $this->receiver], $bus);
        $worker->run();
    }
}

$connection = DriverManager::getConnection(['url' => 'mysql://root:root@127.0.0.1:3306/flow?serverVersion=8.0.31']);
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
    ArrayObject::class => [function (ArrayObject $data) {
        if (is_null($data['number'])) {
            printf("Client %s #%d: error in process\n", $data['client'], $data['id']);
        } else {
            printf("Client %s #%d: result number %d\n", $data['client'], $data['id'], $data['number']);
        }
    }],
]);
