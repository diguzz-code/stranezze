<?php
declare(strict_types=1);

namespace Stranezze\Http;

final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $inputData = null;

    public function method(): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    public function query(string $name, mixed $default = null): mixed
    {
        return $_GET[$name] ?? $default;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string)($_SERVER[$key] ?? '');
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        if ($this->inputData !== null) {
            return $this->inputData;
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            Response::json(['error' => 'Corpo JSON mancante.'], 400);
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            Response::json(['error' => 'JSON non valido.'], 400);
        }

        return $this->inputData = $data;
    }

    public function id(): ?int
    {
        $id = filter_var($this->query('id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $id === false ? null : $id;
    }
}