<?php
declare(strict_types=1);

namespace Stranezze\Infrastructure;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Throwable;

final class Logger
{
    private function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function create(string $directory): self
    {
        try {
            if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new \RuntimeException('Directory log non disponibile.');
            }

            $handler = new RotatingFileHandler($directory . '/app.log', 30, Level::Debug);
            $handler->setFormatter(new LineFormatter(
                "%datetime% %level_name% [%channel%] %message% %context%\n",
                'Y-m-d\\TH:i:sP',
                false,
                true,
                false,
            ));
            return new self(new MonologLogger('app', [$handler]));
        } catch (Throwable) {
            return new self(new MonologLogger('app', [new NullHandler()]));
        }
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        try {
            $this->logger->{$level}($message, $this->normalizeContext($context));
        } catch (Throwable) {
            // Il logging non deve alterare il comportamento HTTP dell'applicazione.
        }
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function normalizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $context[$key] = sprintf(
                    '%s: %s\n%s',
                    $value::class,
                    $value->getMessage(),
                    $value->getTraceAsString(),
                );
            }
        }

        return $context;
    }
}