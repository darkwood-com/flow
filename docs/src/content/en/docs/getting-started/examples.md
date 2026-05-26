---
title: "Examples"
description: "Practical examples of Flow usage with Y-combinator and asynchronous processing."
lead: "Real-world examples demonstrating Flow's capabilities."
date: 2024-01-15T15:21:01+02:00
lastmod: 2024-01-15T15:21:01+02:00
draft: false
images: []
menu:
  docs:
    parent: "getting-started"
weight: 45
toc: true
---

# Examples

This section provides practical examples demonstrating Flow's capabilities with Y-combinator and asynchronous processing.

## HTTP Chunk Processing with Flow and Y-Combinator

This example demonstrates how to adapt the AsyncDecoratorTrait POC to use Flow with Y-combinator and asynchronous processing.

### Overview

The original [AsyncDecoratorTrait POC](https://github.com/alli83/AsyncDecoratorTrait_use_case) from SymfonyLive Paris 2023 demonstrates how to:
- Make HTTP requests and process chunks as they arrive
- Handle 404 errors with retry logic
- Make additional HTTP requests for each item in the main response
- Merge additional data into the main response stream
- Control concurrency

This Flow adaptation achieves the same goals using:
- **Flow's functional programming approach**
- **Y-combinator for recursive processing**
- **Asynchronous processing with multiple drivers**
- **Clean data flow between jobs**

### Architecture

#### Data Models

```php
// HTTP Request Data
class HttpRequestData {
    public function __construct(
        public int $id,
        public string $url,
        public array $options = [],
        public ?string $method = 'GET'
    ) {}
}

// HTTP Response Data
class HttpResponseData {
    public function __construct(
        public int $id,
        public string $content,
        public int $statusCode,
        public array $headers = [],
        public ?array $parsedData = null
    ) {}
}

// Chunk Data (carries data through the flow)
class ChunkData {
    public function __construct(
        public int $id,
        public string $content,
        public bool $isFirst = false,
        public bool $isLast = false,
        public mixed $additionalRequests = null
    ) {}
}

// Final User Data
class UserData {
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?array $availabilities = null,
        public ?array $posts = null
    ) {}
}
```

#### Flow Jobs

1. **HTTP Request Job**: Makes the initial HTTP request
2. **Parse Response Job**: Parses the response and handles 404 errors
3. **Process Chunks Job**: Uses Y-combinator to recursively process chunks
4. **Make Additional Requests Job**: Makes additional HTTP requests using Y-combinator
5. **Merge Data Job**: Merges additional data with main response
6. **Finalize Job**: Outputs the final results

#### Y-Combinator Usage

The Y-combinator is used in two key places:

1. **Chunk Processing**: Recursively processes chunks and prepares additional requests
2. **Additional Requests**: Recursively makes additional HTTP requests

```php
// Y-combinator wrapper
$Ywrap = static function (callable $func, callable $wrapperFunc): JobInterface {
    $wrappedFunc = static fn ($recurse) => $wrapperFunc(static fn (...$args) => $func($recurse)(...$args));
    return new YJob($wrappedFunc);
};

// Usage in Flow
yield new YFlow($processChunksRecursively);
yield [$makeAdditionalRequestsAsync];
```

### Key Features

#### 1. Asynchronous Processing
- Uses Flow's async drivers (AmpDriver, ReactDriver)
- Processes multiple requests concurrently
- Handles delays and timeouts gracefully

#### 2. Error Handling
- Handles 404 errors with automatic retry
- Graceful fallback to alternative URLs
- Continues processing even if some requests fail

#### 3. Data Flow
- Clean data transformation through the flow
- Type-safe data models
- Immutable data structures

#### 4. Concurrency Control
- Processes multiple users concurrently
- Makes additional requests in parallel
- Efficient resource utilization

### Usage

```bash
php examples/httpchunkflow.php
```

### Comparison with AsyncDecoratorTrait

| Feature | AsyncDecoratorTrait | Flow + Y-Combinator |
|---------|-------------------|-------------------|
| **Approach** | Imperative, callback-based | Functional, flow-based |
| **Recursion** | Manual loop management | Y-combinator |
| **Async** | Symfony HttpClient | Flow drivers |
| **Data Flow** | Mutable state | Immutable data models |
| **Error Handling** | Try-catch blocks | Flow error jobs |
| **Concurrency** | Manual management | Built-in Flow support |
| **Testing** | Complex setup | Simple data flow testing |

### Benefits of Flow Adaptation

1. **Functional Programming**: Clean, composable functions
2. **Y-Combinator**: Elegant recursive processing
3. **Type Safety**: Strong typing with data models
4. **Async by Design**: Built-in asynchronous processing
5. **Testability**: Easy to test individual jobs
6. **Maintainability**: Clear separation of concerns
7. **Extensibility**: Easy to add new processing steps

### Example Output

```
Using Flow\Driver\AmpDriver driver
Starting HTTP chunk processing with Flow and Y-combinator...

*. #1 - Making HTTP request to https://jsonplaceholder.typicode.com/users
*. #2 - Making HTTP request to https://jsonplaceholder.typicode.com/users/404
*. #3 - Making HTTP request to https://jsonplaceholder.typicode.com/users
*. #1 - HTTP request completed with status 200 (took 1.0 seconds)
.* #1 - Parsing HTTP response
..* #1 - Processing chunks with Y-combinator
...* #101 - Making additional request to https://jsonplaceholder.typicode.com/users/1/todos
...* #1101 - Making additional request to https://jsonplaceholder.typicode.com/users/1/posts
....* Merging additional data with main response
.....* Finalizing results
User #1: John Doe (john@example.com)
  - Todos: 3 items
  - Posts: 2 items
    Todo examples: Todo 1, Todo 2, Todo 3
    Post examples: Post 1, Post 2
Processing completed successfully!
```

### Conclusion

This Flow adaptation demonstrates how to:
- Convert imperative, callback-based code to functional, flow-based code
- Use Y-combinator for elegant recursive processing
- Leverage Flow's asynchronous capabilities
- Maintain the same functionality while improving code quality and maintainability

The result is a more elegant, testable, and maintainable solution that achieves the same goals as the original AsyncDecoratorTrait POC.

## Comparison Demo

A simple demonstration comparing the AsyncDecoratorTrait approach with Flow + Y-Combinator:

```bash
php examples/comparison_demo.php
```

This demo shows the key differences between the two approaches and highlights the benefits of using Flow for functional programming and asynchronous processing.
