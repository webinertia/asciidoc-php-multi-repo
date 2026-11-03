<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Serve;

use RuntimeException;

use function explode;
use function fclose;
use function fread;
use function fwrite;
use function is_resource;
use function round;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function stream_select;
use function stream_set_timeout;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;

use const STREAM_SERVER_BIND;
use const STREAM_SERVER_LISTEN;

/**
 * A single threaded HTTP/1.1 static file server.
 *
 * `php -S` is deliberately not used: the package ships a console binary, not a PHP runtime wrapper,
 * and shelling out to a second PHP process would put a binary the package does not control between
 * the command and the site. The loop is driven by the caller (`serveOnce`) so `watch` can serve
 * requests between rebuilds and `serve` can serve them until it is stopped.
 *
 * One request is handled per call, connections are closed immediately, and a request that never
 * arrives cannot stall the caller for longer than the read timeout.
 */
final class StaticServer
{
    private const int MAX_REQUEST_BYTES = 16_384;

    private const int READ_TIMEOUT_SECONDS = 5;

    /** @var null|resource */
    private mixed $socket = null;

    private int $port = 0;

    public function __construct(
        private readonly StaticSiteHandler $handler,
        private readonly string $host = '127.0.0.1',
        private readonly int $requestedPort = 0,
    ) {}

    public function isStarted(): bool
    {
        return is_resource($this->socket);
    }

    public function port(): int
    {
        return $this->port;
    }

    /**
     * Waits up to $timeout for one connection and answers it. Returns true when a request was
     * handled, false when the wait elapsed with nothing to do.
     *
     * @throws RuntimeException when a resolved file cannot be read
     */
    public function serveOnce(float $timeout): bool
    {
        if (! is_resource($this->socket)) {
            return false;
        }

        $read   = [$this->socket];
        $write  = null;
        $except = null;
        $whole  = (int) $timeout;
        $micro  = (int) round(($timeout - $whole) * 1_000_000);

        $ready = @stream_select($read, $write, $except, $whole, $micro);

        if (false === $ready || 0 === $ready) {
            return false;
        }

        $connection = @stream_socket_accept(
            socket : $this->socket,
            timeout: 0.0,
        );

        if (false === $connection) {
            return false;
        }

        try {
            $this->respond($connection);
        } finally {
            fclose($connection);
        }

        return true;
    }

    /**
     * @throws RuntimeException when the address cannot be bound
     */
    public function start(): void
    {
        if (is_resource($this->socket)) {
            return;
        }

        $address = sprintf('tcp://%s:%d', $this->host, $this->requestedPort);

        $errorMessage = '';

        $socket = @stream_socket_server(
            address      : $address,
            error_message: $errorMessage,
            flags        : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        );

        if (false === $socket) {
            throw new RuntimeException(sprintf(
                'Unable to listen on "%s": %s.',
                $address,
                '' === $errorMessage ? 'unknown error' : $errorMessage,
            ));
        }

        $this->socket = $socket;

        $name = @stream_socket_get_name(
            socket: $socket,
            remote: false,
        );

        $this->port = $this->portOf(false === $name ? '' : $name);
    }

    public function stop(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    public function url(): string
    {
        $host = '0.0.0.0' === $this->host ? 'localhost' : $this->host;

        return sprintf('http://%s:%d/', $host, $this->port);
    }

    /**
     * @return array{0: null|string, 1: string}
     */
    private function parseRequestLine(string $request): array
    {
        $end = strpos(
            haystack: $request,
            needle  : "\r\n",
        );
        $line = false === $end
            ? $request
            : substr(
                string: $request,
                offset: 0,
                length: $end,
            );
        $parts = explode(' ', $line);

        $method = $parts[0];
        $target = $parts[1] ?? '';

        if ('' === $method || ! str_starts_with($target, '/')) {
            return [null, ''];
        }

        return [$method, $target];
    }

    private function portOf(string $name): int
    {
        $colon = strrpos(
            haystack: $name,
            needle  : ':',
        );

        if (false === $colon) {
            return 0;
        }

        return (int) substr(
            string: $name,
            offset: $colon + 1,
        );
    }

    /**
     * @param resource $connection
     */
    private function readRequest(mixed $connection): string
    {
        stream_set_timeout($connection, self::READ_TIMEOUT_SECONDS);

        $request = '';

        while (! str_contains($request, "\r\n\r\n") && strlen($request) < self::MAX_REQUEST_BYTES) {
            $chunk = @fread(
                stream: $connection,
                length: 1024,
            );

            if (false === $chunk || '' === $chunk) {
                break;
            }

            $request .= $chunk;
        }

        return $request;
    }

    /**
     * @param resource $connection
     *
     * @throws RuntimeException when a resolved file cannot be read
     */
    private function respond(mixed $connection): void
    {
        [$method, $target] = $this->parseRequestLine($this->readRequest($connection));

        $response = null === $method
            ? StaticResponse::badRequest('Malformed request line')
            : $this->handler->handle($method, $target);

        @fwrite($connection, $response->headerBlock());

        if ('head' !== strtolower((string) $method)) {
            @fwrite($connection, $response->body());
        }
    }
}
