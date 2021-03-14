<?php

namespace RFBP\Transport;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class FromTransportIdStamp implements StampInterface
{
    private $id;

    /**
     * @param mixed $id some "identifier" of the message in a transport
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }
}
