<?php
declare(strict_types=1);

namespace Stranezze\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use Stranezze\Infrastructure\DatabaseFactory;
use Stranezze\Infrastructure\UserRepository;
use Stranezze\Tests\TestCase;

final class ApiSmokeTest extends TestCase
{
    private static string $databasePath;
    private static int $port;
    /** @var resource */
    private static $process;
    /** @var array<int, resource> */
    private static array $pipes = [];
    private static PDO $database;

    public static function setUpBeforeClass(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stranezze-api-');
        if ($path === false) {
            self::fail('Impossibile creare il database API temporaneo.');
        }
        self::$databasePath = $path;
        $migrationProcess = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/vendor/bin/phinx', 'migrate', '-e', 'development'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $migrationPipes,
            dirname(__DIR__, 2),
            ['STRANEZZE_DB_PATH' => self::$databasePath, 'APP_ENV' => 'testing'],
        );
        if (!is_resource($migrationProcess)) {
            self::fail('Impossibile applicare le migration al database API temporaneo.');
        }
        fclose($migrationPipes[0]);
        $migrationOutput = stream_get_contents($migrationPipes[1]) . stream_get_contents($migrationPipes[2]);
        fclose($migrationPipes[1]);
        fclose($migrationPipes[2]);
        if (proc_close($migrationProcess) !== 0) {
            self::fail('Migration API fallite: ' . $migrationOutput);
        }
        self::$database = DatabaseFactory::create($path);
        (new UserRepository(self::$database))->create(
            'api-user',
            password_hash('correct-password', PASSWORD_DEFAULT),
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        self::$process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:0', 'router.php'],
            $descriptors,
            self::$pipes,
            dirname(__DIR__, 2),
            ['STRANEZZE_DB_PATH' => self::$databasePath, 'APP_ENV' => 'testing'],
        );
        if (!is_resource(self::$process)) {
            self::fail('Impossibile avviare il server PHP per lo smoke test.');
        }

        foreach ([1, 2] as $pipe) {
            stream_set_blocking(self::$pipes[$pipe], false);
        }
        $output = '';
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $output .= stream_get_contents(self::$pipes[1]);
            $output .= stream_get_contents(self::$pipes[2]);
            if (preg_match('/127\.0\.0\.1:(\d+)/', $output, $matches) === 1) {
                self::$port = (int)$matches[1];
                if (self::waitForServer()) {
                    return;
                }
            }
            usleep(20000);
        }

        self::stopServer();
        self::fail('Il server PHP non è diventato pronto. Output: ' . $output);
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
        foreach ([self::$databasePath, self::$databasePath . '-wal', self::$databasePath . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::$database->exec('DELETE FROM login_attempts');
    }

    #[Test]
    public function sessionWithoutAuthenticationReturnsFalse(): void
    {
        $response = $this->request('GET', '/api?action=session');
        $this->assertApiResponse($response, 200);
        self::assertFalse($response['json']['authenticated']);
    }

    #[Test]
    public function invalidCredentialsReturnUnauthorized(): void
    {
        $response = $this->request('POST', '/api?action=login', [
            'username' => 'api-user',
            'password' => 'wrong-password',
        ]);
        $this->assertApiResponse($response, 401);
    }

    #[Test]
    public function statsWithoutAuthenticationReturnsUnauthorized(): void
    {
        $response = $this->request('GET', '/api?action=stats');
        $this->assertApiResponse($response, 401);
    }

    #[Test]
    public function unsupportedPatchReturnsMethodNotAllowed(): void
    {
        $response = $this->request('PATCH', '/api');
        $this->assertApiResponse($response, 405);
    }

    #[Test]
    public function sixthFailedLoginIsRateLimited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->request('POST', '/api?action=login', [
                'username' => 'api-user',
                'password' => 'wrong-password',
            ]);
            $this->assertApiResponse($response, 401);
        }

        $response = $this->request('POST', '/api?action=login', [
            'username' => 'api-user',
            'password' => 'wrong-password',
        ]);
        $this->assertApiResponse($response, 429);
        self::assertArrayHasKey('retry-after', $response['headers']);
    }

    /** @return array{status: int, headers: array<string, string>, json: array<string, mixed>} */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $curl = curl_init('http://127.0.0.1:' . self::$port . $path);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => $payload === null ? [] : ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        $response = curl_exec($curl);
        if ($response === false) {
            self::fail('Richiesta HTTP fallita: ' . curl_error($curl));
        }
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $headers = [];
        foreach (explode("\r\n", substr($response, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        $body = json_decode(substr($response, $headerSize), true, 512, JSON_THROW_ON_ERROR);
        return ['status' => $status, 'headers' => $headers, 'json' => $body];
    }

    /** @param array{status: int, headers: array<string, string>, json: array<string, mixed>} $response */
    private function assertApiResponse(array $response, int $status): void
    {
        self::assertSame($status, $response['status']);
        self::assertSame('nosniff', $response['headers']['x-content-type-options'] ?? null);
        self::assertSame('DENY', $response['headers']['x-frame-options'] ?? null);
        self::assertSame('same-origin', $response['headers']['referrer-policy'] ?? null);
        self::assertArrayHasKey('permissions-policy', $response['headers']);
        self::assertArrayHasKey('content-security-policy', $response['headers']);
    }

    private static function waitForServer(): bool
    {
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $curl = curl_init('http://127.0.0.1:' . self::$port . '/api?action=session');
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 1,
            ]);
            $response = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            if ($response !== false && $status === 200) {
                return true;
            }
            usleep(20000);
        }

        return false;
    }

    private static function stopServer(): void
    {
        if (!is_resource(self::$process)) {
            return;
        }
        proc_terminate(self::$process);
        foreach (self::$pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close(self::$process);
        self::$pipes = [];
        self::$process = null;
    }
}