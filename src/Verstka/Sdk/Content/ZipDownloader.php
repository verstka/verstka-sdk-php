<?php

declare(strict_types=1);

namespace Verstka\Sdk\Content;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;
use Verstka\Sdk\Exception\VerstkaApiError;
use Verstka\Sdk\Exception\VerstkaContentTooLargeError;

final class ZipDownloader
{
    private const CHUNK_SIZE = 8192;

    public function __construct(
        private readonly ClientInterface $httpClient,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function download(
        string $url,
        string $destPath,
        int $maxSize,
        float $timeout,
        array $headers = [],
    ): void {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'timeout' => $timeout,
                'stream' => true,
            ]);
        } catch (GuzzleException $exception) {
            throw new VerstkaApiError('Failed to download content: ' . $exception->getMessage());
        }

        $statusCode = $response->getStatusCode();
        $mapped = $this->statusToError($statusCode);
        if ($mapped !== null) {
            throw $mapped;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new VerstkaApiError(
                'Failed to download content: HTTP ' . $statusCode,
                statusCode: $statusCode,
            );
        }

        $contentLength = $response->getHeaderLine('Content-Length');
        if ($contentLength !== '' && is_numeric($contentLength)) {
            $this->checkSize((int) $contentLength, $maxSize);
        }

        $stream = $response->getBody();
        $this->writeStream($stream, $destPath, $maxSize);
    }

    private function statusToError(int $status): ?VerstkaApiError
    {
        return match ($status) {
            403 => new VerstkaApiError('Access denied: invalid API key or signature', statusCode: 403),
            404 => new VerstkaApiError(
                'Content not found: invalid material_id or expired content',
                statusCode: 404,
            ),
            500 => new VerstkaApiError('Server error: content service unavailable', statusCode: 500),
            default => null,
        };
    }

    private function writeStream(StreamInterface $stream, string $destPath, int $maxSize): void
    {
        $downloaded = 0;
        $handle = fopen($destPath, 'wb');
        if ($handle === false) {
            throw new VerstkaApiError('Failed to open destination file: ' . $destPath);
        }

        try {
            while (!$stream->eof()) {
                $chunk = $stream->read(self::CHUNK_SIZE);
                if ($chunk === '') {
                    continue;
                }
                fwrite($handle, $chunk);
                $downloaded += strlen($chunk);
                $this->checkSize($downloaded, $maxSize);
            }
        } finally {
            fclose($handle);
        }
    }

    private function checkSize(int $current, int $limit): void
    {
        if ($current > $limit) {
            throw new VerstkaContentTooLargeError(
                sprintf('Content file too large: %d bytes (max: %d)', $current, $limit),
            );
        }
    }
}
