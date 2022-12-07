<?php

declare(strict_types=1);

namespace RFBP\Examples\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class DoctrineIpTransportIdStamp implements StampInterface
{
    private string $id;

    /**
     * @param string $id some "identifier" of the transport name
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
