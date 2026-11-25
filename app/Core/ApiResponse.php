<?php
declare(strict_types=1);

namespace UniGuide\App\Core;

final class ApiResponse
{
    public static function success($data, ?array $meta = null, int $status = 200): void
    {
        $response = ['data' => $data];
        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        self::send($response, $status);
    }

    public static function error(string $code, string $message, int $status): void
    {
        self::send([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    private static function send(array $payload, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            echo json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
        } catch (\JsonException $exception) {
            error_log('[UniGuide] Failed to encode an API response: ' . $exception->getMessage());
            http_response_code(500);
            echo '{"error":{"code":"internal_error","message":"The request could not be completed."}}';
        }

        exit;
    }
}
