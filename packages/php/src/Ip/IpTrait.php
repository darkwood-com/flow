<?php

declare(strict_types=1);

namespace RFBP\Ip;

use RuntimeException;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IpIdStamp;

trait IpTrait
{
    private function getIpId(Ip $ip): mixed
    {
        /** @var ?IpIdStamp $stamp */
        $stamp = $ip->last(IpIdStamp::class);

        if (is_null($stamp) || is_null($stamp->getId())) {
            throw new RuntimeException('Transport does not define Id for Ip');
        }

        return $stamp->getId();
    }
}
