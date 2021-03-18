<?php

namespace RFBP\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class FromTransportIdStamp implements StampInterface
{
    private $id;

    /**
     * @param mixed $id some "identifier" of the transport name
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
