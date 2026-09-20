<?php
declare(strict_types=1);

namespace Stranezze\Http;

final class Response
{
    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @param list<string> $headers @param iterable<array<string, mixed>> $rows */
    public static function csv(string $filename, array $headers, iterable $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'wb');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}