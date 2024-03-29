<?php

declare(strict_types=1);

namespace Flow\Test\Examples\Transport;

use ArrayObject;
use Doctrine\DBAL\DriverManager;
use Flow\Examples\Stamp\DoctrineIpTransportIdStamp;
use Flow\Examples\Transport\DoctrineIpTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

use function count;

class DoctrineIpTransportTest extends TestCase
{
    public function testMultipleClientTransport(): void
    {
        $connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $supervisorTransport = new DoctrineIpTransport($connection);

        $clientTransports = [];
        for ($i = 0; $i < 5; $i++) {
            $clientTransports[] = new DoctrineIpTransport($connection, uniqid('transport_', true));
        }

        for ($i = 0; $i < 20; $i++) {
            $data = new ArrayObject(['number' => 1]);
            $clientTransports[$i % 5]->send(new Envelope($data));
        }

        $ips = [];
        do {
            foreach ($ips as $ip) {
                $supervisorTransport->ack($ip);
                $data = $ip->getMessage();
                self::assertSame(1, $data['number']);
                $data['number'] = 2;
                $supervisorTransport->send(Envelope::wrap($data, [$ip->last(DoctrineIpTransportIdStamp::class)]));
            }
            $ips = $supervisorTransport->get();
        } while (count($ips) > 0);

        foreach ($clientTransports as $clientTransport) {
            $ips = $clientTransport->get();
            foreach ($ips as $ip) {
                $clientTransport->ack($ip);
                $data = $ip->getMessage();
                self::assertSame(2, $data['number']);
            }
        }
    }
}
