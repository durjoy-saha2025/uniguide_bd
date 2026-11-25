<?php
declare(strict_types=1);

namespace UniGuide\App\Core;

final class Request
{
    public static function jsonBody(int $maximumBytes = 65536): array
    {
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;
        if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > $maximumBytes) {
            ApiResponse::error('payload_too_large', 'The request body is too large.', 413);
        }

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || $rawBody === '' || strlen($rawBody) > $maximumBytes) {
            ApiResponse::error('invalid_json', 'A JSON request body is required.', 400);
        }

        try {
            $decoded = json_decode($rawBody);
        } catch (\JsonException $exception) {
            ApiResponse::error('invalid_json', 'The JSON request body is malformed.', 400);
        }

        if (!$decoded instanceof \stdClass) {
            ApiResponse::error('invalid_json', 'The JSON request body must be an object.', 400);
        }

        return json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function requireMethod(string $method): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            header('Allow: ' . $method);
            ApiResponse::error('method_not_allowed', 'The request method is not allowed.', 405);
        }
    }
}
