<?php

declare(strict_types=1);

namespace Flow\Driver;

use Flow\DriverInterface;
use Io\Poll\Backend;
use Io\Poll\Context;
use Io\Poll\Event;
use Io\Poll\FailedPollWaitException;
use Io\Poll\InvalidHandleException;
use Io\Poll\PollException;
use StreamPollHandle;
use Time\Duration;

/**
 * Cooperative generator jobs backed by PHP's native descriptor polling.
 *
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
final class PollDriver implements DriverInterface
{
    use StreamDriverTrait;

    public function __construct(private readonly Backend $backend = Backend::Auto) {}

    private function pollStreams(array $read, array $write, ?float $timeoutSeconds): array
    {
        $registrations = [];
        $readyRead = [];
        $readyWrite = [];
        foreach ([[$read, Event::Read], [$write, Event::Write]] as [$streams, $event]) {
            foreach ($streams as $stream) {
                if (!is_resource($stream)) {
                    throw new InvalidHandleException('A stream was closed while Flow was waiting for it.');
                }

                $id = (int) $stream;
                if ($event === Event::Read && stream_get_meta_data($stream)['unread_bytes'] > 0) {
                    // kqueue/epoll cannot see bytes already buffered by PHP.
                    $readyRead[$id] = true;
                    continue;
                }

                $registrations[$id]['stream'] = $stream;
                $registrations[$id]['events'][$event->name] = $event;
            }
        }

        if ($registrations === []) {
            return [$readyRead, $readyWrite];
        }

        $context = new Context($this->backend);
        try {
            foreach ($registrations as $id => $registration) {
                $context->add(
                    new StreamPollHandle($registration['stream']),
                    array_values($registration['events']),
                    $id,
                );
            }

            $timeout = match (true) {
                $readyRead !== [] => Duration::fromMicroseconds(0),
                $timeoutSeconds === null => null,
                // Keep very long waits representable by the native backend.
                default => Duration::fromMicroseconds((int) ceil(min($timeoutSeconds, 86_400.0) * 1_000_000)),
            };
            $ready = $context->wait($timeout);
            foreach ($ready as $watcher) {
                $id = $watcher->getData();
                if ($watcher->hasTriggered(Event::Error)) {
                    throw new PollException('Native polling reported an I/O error on a watched stream.');
                }

                $hangUp = $watcher->hasTriggered(Event::HangUp);
                if ($watcher->hasTriggered(Event::Read) || $watcher->hasTriggered(Event::ReadHangUp) || $hangUp) {
                    $readyRead[$id] = true;
                }
                if ($watcher->hasTriggered(Event::Write) || $hangUp) {
                    $readyWrite[$id] = true;
                }
            }

            return [$readyRead, $readyWrite];
        } catch (FailedPollWaitException $exception) {
            if ($exception->getCode() === FailedPollWaitException::ERROR_INTERRUPTED) {
                // Re-enter the scheduler so deadlines are recomputed after the signal.
                return [$readyRead, $readyWrite];
            }

            throw $exception;
        } finally {
            // Context owns its registrations. Release them before resuming user jobs,
            // which may close or reuse streams. The caller retains ownership of streams.
            unset($ready, $watcher, $context);
        }
    }
}
