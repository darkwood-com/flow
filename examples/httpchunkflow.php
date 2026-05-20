<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ParallelDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Model\ChunkData;
use Flow\Examples\Model\HttpRequestData;
use Flow\Examples\Model\HttpResponseData;
use Flow\Examples\Model\UserData;
use Flow\Flow\YFlow;
use Flow\FlowFactory;
use Flow\Ip;
use Flow\Job\YJob;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

// Constants
const BASE = 'https://jsonplaceholder.typicode.com';
const USERS = BASE . '/users';
const TODOS = BASE . '/todos';
const POSTS = BASE . '/posts';

// Driver selection
$driver = match (random_int(1, 3)) {
    1 => new AmpDriver(),
    2 => new ReactDriver(),
    3 => new FiberDriver(),
    // 4 => new SwooleDriver(),
    // 5 => new SpatieDriver(),
    // 6 => new ParallelDriver(),
};

printf("Using %s driver\n", $driver::class);

$startTime = microtime(true);

// Create real HTTP client
$client = HttpClient::create([
    'timeout' => 20.0,
    'headers' => ['Accept' => 'application/json'],
]);

// Shared state for accumulating results and metrics
$state = [
    'merged' => [], // Will accumulate merged UserData objects
    'dispatched' => [], // Track dispatched requests to avoid duplicates
    'metrics' => ['retry' => 0, 'errors' => 0, 'users' => 0],
];

// Helper function for relative timestamps
function ms(float $startTime): int
{
    return (int) ((microtime(true) - $startTime) * 1000);
}

// Job 1: Make initial HTTP requests
$httpRequestJob = static function (HttpRequestData $requestData) use ($client): ResponseInterface {
    printf("*. #%d GET %s ... started\n", $requestData->id, $requestData->url);

    try {
        return $client->request('GET', $requestData->url, [
            'headers' => ['Accept' => 'application/json'],
            'user_data' => ['id' => $requestData->id],
        ]);
    } catch (TransportExceptionInterface $e) {
        printf("*. #%d ERROR: %s\n", $requestData->id, $e->getMessage());

        throw $e;
    }
};

// Job 2: Process streaming responses and handle 404 errors
$parseResponseJob = static function (ResponseInterface $response) use ($client, &$state): ChunkData {
    $userData = $response->getInfo('user_data');
    $id = (int) ($userData['id'] ?? 1);

    try {
        $statusCode = $response->getStatusCode();

        if ($statusCode === 404) {
            printf(".* #%d 404 -> retry /users/1\n", $id);
            $state['metrics']['retry']++;

            // Make real retry request
            $retryResponse = $client->request('GET', USERS . '/1', [
                'headers' => ['Accept' => 'application/json'],
                'user_data' => ['id' => $id],
            ]);
            $response = $retryResponse;
            $statusCode = $response->getStatusCode();
        }

        $content = $response->getContent();
        $parsedData = json_decode($content, true);

        printf(".* #%d %d in %dms\n", $id, $statusCode, ms($GLOBALS['startTime']));

        return new ChunkData($id, $content, true, true, $parsedData);
    } catch (ClientExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
        printf(".* #%d ERROR: %s\n", $id, $e->getMessage());
        $state['metrics']['errors']++;

        throw $e;
    }
};

// Job 3: Process chunks recursively using Y-combinator
$processChunksJob = static function ($processChunks) {
    return static function (ChunkData $chunkData): ChunkData {
        printf("..* #%d chunks: parsing via Y\n", $chunkData->id);

        if (!$chunkData->additionalRequests || !is_array($chunkData->additionalRequests)) {
            return $chunkData;
        }

        $additionalRequests = [];

        // Handle both single user object and array of users
        $users = $chunkData->additionalRequests;

        // If it's a single user object (not indexed array), wrap it in an array
        if (isset($users['id']) && !isset($users[0])) {
            $users = [$users];
        }

        foreach ($users as $user) {
            if (!is_array($user) || !isset($user['id'])) {
                continue;
            }
            $userId = $user['id'];

            // Create additional requests with explicit metadata and deduplication
            $todoKey = "{$userId}:todos";
            $postsKey = "{$userId}:posts";

            if (!isset($GLOBALS['state']['dispatched'][$todoKey])) {
                $GLOBALS['state']['dispatched'][$todoKey] = true;
                printf(
                    "...* #%d QUEUED %s\n",
                    $chunkData->id * 100 + $userId,
                    USERS . "/{$userId}/todos"
                );
                $additionalRequests[] = new HttpRequestData(
                    $chunkData->id * 100 + $userId,
                    USERS . "/{$userId}/todos",
                    ['userId' => $userId, 'type' => 'todos']
                );
            }

            if (!isset($GLOBALS['state']['dispatched'][$postsKey])) {
                $GLOBALS['state']['dispatched'][$postsKey] = true;
                printf(
                    "...* #%d QUEUED %s\n",
                    $chunkData->id * 100 + $userId + 1000,
                    USERS . "/{$userId}/posts"
                );
                $additionalRequests[] = new HttpRequestData(
                    $chunkData->id * 100 + $userId + 1000,
                    USERS . "/{$userId}/posts",
                    ['userId' => $userId, 'type' => 'posts']
                );
            }
        }

        $chunkData->additionalRequests = $additionalRequests;

        return $chunkData;
    };
};

// Job 4: Make additional HTTP requests
$fetchExtrasJob = new YJob(static function ($fetchExtras) use ($client) {
    return static function (ChunkData $chunkData) use ($client): ChunkData {
        if (!$chunkData->additionalRequests || !is_array($chunkData->additionalRequests)) {
            return $chunkData;
        }

        $responses = [];
        foreach ($chunkData->additionalRequests as $request) {
            if ($request instanceof HttpRequestData) {
                printf("...* #%d START %s\n", $request->id, $request->url);

                try {
                    $response = $client->request('GET', $request->url, [
                        'headers' => ['Accept' => 'application/json'],
                    ]);

                    $requestStart = microtime(true);
                    $content = $response->getContent();
                    $elapsed = round((microtime(true) - $requestStart) * 1000);

                    printf(".* #%d %d in %dms\n", $request->id, $response->getStatusCode(), $elapsed);

                    // Store metadata in response content as a special format
                    $metadata = json_encode([
                        'data' => json_decode($content, true),
                        'metadata' => $request->options,
                    ]);

                    $responses[] = new HttpResponseData($request->id, $metadata, $response->getStatusCode());
                } catch (ClientExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
                    printf(".* #%d ERROR: %s\n", $request->id, $e->getMessage());
                    $GLOBALS['state']['metrics']['errors']++;
                    // Continue with other requests
                }
            }
        }

        $chunkData->additionalRequests = $responses;

        return $chunkData;
    };
});

// Job 5: Merge Data - Accumulate into shared state (idempotent)
$mergeJob = static function (ChunkData $chunkData) use (&$state): ChunkData {
    printf("....* merging data\n");

    $originalUsers = json_decode($chunkData->content, true);
    $additionalResponses = $chunkData->additionalRequests ?? [];

    if (!$originalUsers || !is_array($originalUsers)) {
        return $chunkData;
    }

    foreach ($originalUsers as $user) {
        // Skip invalid user structures (handles single user objects from retry)
        if (!is_array($user) || !isset($user['id'])) {
            continue;
        }

        $userId = $user['id'];

        // Initialize user in state if not exists
        if (!isset($state['merged'][$userId])) {
            $name = $user['name'] ?? 'Unknown User';
            $email = $user['email'] ?? 'unknown@example.com';
            $state['merged'][$userId] = new UserData($userId, $name, $email, [], []);
        }

        // Process additional responses for this user
        foreach ($additionalResponses as $response) {
            if ($response instanceof HttpResponseData) {
                $responseContent = json_decode($response->content, true);

                if (isset($responseContent['metadata'], $responseContent['data'])) {
                    $responseUserId = $responseContent['metadata']['userId'];
                    $type = $responseContent['metadata']['type'];

                    if ($responseUserId === $userId) {
                        $responseData = $responseContent['data'];

                        if ($type === 'todos') {
                            $state['merged'][$userId]->todos = $responseData;
                        } elseif ($type === 'posts') {
                            $state['merged'][$userId]->posts = $responseData;
                        }
                    }
                }
            }
        }
    }

    return $chunkData;
};

// Job 6: Finalize & Output (placeholder - will be called once after flow completes)
$finalizeJob = static function (ChunkData $chunkData): ChunkData {
    // This job just passes through - final output happens after flow completes
    return $chunkData;
};

// Create Flow
$flow = (new FlowFactory())->create(static function () use (
    $httpRequestJob,
    $parseResponseJob,
    $processChunksJob,
    $fetchExtrasJob,
    $mergeJob,
    $finalizeJob
) {
    yield [$httpRequestJob];
    yield [$parseResponseJob];
    yield new YFlow($processChunksJob);
    yield [$fetchExtrasJob];
    yield [$mergeJob];
    yield [$finalizeJob];
}, ['driver' => $driver]);

// Test scenarios
$testScenarios = [
    new HttpRequestData(1, USERS),
    new HttpRequestData(2, USERS . '/404'),
    new HttpRequestData(3, TODOS),
];

printf("Starting HTTP chunk processing with Flow and Y-combinator...\n\n");

foreach ($testScenarios as $scenario) {
    $ip = new Ip($scenario);
    $flow($ip);
}

$flow->await();

// Single finalize pass after all flows complete
printf("....* merging data\n");
printf(".....* finalizing results\n");

// Show only first 2 users for demo
$mergedUsers = array_values($state['merged']);
$displayUsers = array_slice($mergedUsers, 0, 2);

foreach ($displayUsers as $user) {
    printf("User #%d: %s (%s)\n", $user->id, $user->name, $user->email);
    printf("  - Todos: %d items\n", count($user->todos));
    printf("  - Posts: %d items\n", count($user->posts));

    if (count($user->todos) > 0) {
        printf("    Todo examples: %s\n", implode(', ', array_column(array_slice($user->todos, 0, 3), 'title')));
    }
    if (count($user->posts) > 0) {
        printf("    Post examples: %s\n", implode(', ', array_column(array_slice($user->posts, 0, 2), 'title')));
    }
}

// Single definitive summary line
$state['metrics']['users'] = count($mergedUsers);
$duration = round(microtime(true) - $startTime, 2);
printf(
    "DONE driver=%s duration=%.2fs users=%d retry=%d errors=%d\n",
    $driver::class,
    $duration,
    $state['metrics']['users'],
    $state['metrics']['retry'],
    $state['metrics']['errors']
);
