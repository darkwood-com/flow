<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\FlowInterface;
use Flow\Ip;
use Symfony\Component\Webhook\Client\RequestParserInterface;

class WebhookFlow extends FlowInterface
{
    public function __construct(RequestParserInterface $requestParserInterface)
    {
    }

    public function __invoke(Ip $ip, ?Closure $callback = null): void
    {
        
    }

    public function fn(FlowInterface $flow): FlowInterface
    {
        return $this->flow->fn($flow);
    }
}
