<?php

namespace RFBP;

use Amp\Loop;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class Supervisor
{
    static int $ipId = 0;

    protected $ips;

    public function __construct(
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        private $pipes,
        private $error
    ) {
        $this->ips = [];
    }

    public function start() {
        Loop::run(function() {
            Loop::repeat(1000, function() {
                /*foreach ($this->producer->getDatas() as $struct) {
                    $this->ips[] = [
                        'id' => self::$ipId++,
                        'pipeIndex' => 0,
                        'struct' => $struct,
                    ];
                }

                foreach ($this->ips as $ipIndex => $ip) {
                    if($ip['pipeIndex'] < count($this->pipes)) {
                        if($this->pipes[$ip['pipeIndex']]->run($ip))
                        {
                            $this->ips[$ipIndex]['pipeIndex']++;
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