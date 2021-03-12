<?php

namespace RFBP;

use Amp\Loop;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class Supervisor
{
    /** @var array<IP> */
    protected $ips;

    public function __construct(
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        /** @var array<Rail> */
        private $rails,
        private ?Rail $error = null
    ) {
        $this->ips = [];
    }

    public function start() {
        Loop::run(function() {
            Loop::repeat(1, function() {
                /*foreach ($this->producer->getDatas() as $struct) {
                    $this->ips[] = [
                        'id' => self::$ipId++,
                        'railIndex' => 0,
                        'struct' => $struct,
                    ];
                }

                foreach ($this->ips as $ipIndex => $ip) {
                    if($ip['railIndex'] < count($this->rails)) {
                        if($this->rails[$ip['railIndex']]->run($ip))
                        {
                            $this->ips[$ipIndex]['railIndex']++;
                        }
                    } else {
                        $this->consumer->receive($ip['struct']);
                        unset($this->ips[$ipIndex]);
                    }
                    //print_r($ip);
                }*/

                echo "******* Tick *******\n";
            });
        });
    }
}