<?php

declare(strict_types=1);

namespace Imper86\DynamoDBClientBundleTests;

use Http\Mock\Client as MockClient;
use Imper86\DynamoDBClient\DynamoDBClientInterface;
use Imper86\DynamoDBClient\Exception\ExceptionInterface;
use Imper86\DynamoDBClient\Exception\MissingCredentialsException;
use Imper86\DynamoDBClientBundle\DynamoDBClientBundle;
use Imper86\DynamoDBClientBundleTests\Fixtures\ClassConsumer;
use Imper86\DynamoDBClientBundleTests\Fixtures\InterfaceConsumer;
use Imper86\DynamoDBClientBundleTests\Fixtures\TestKernel;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Exception;
use Override;

use function getenv;
use function is_string;
use function putenv;

/**
 * @internal
 */
#[CoversClass(DynamoDBClientBundle::class)]
final class DynamoDBClientBundleTest extends TestCase
{
    private const array ENV_VARIABLES = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_SESSION_TOKEN'];

    private const array PLACEHOLDER_VARIABLES = [
        'DYNAMODB_BUNDLE_TEST_REGION',
        'DYNAMODB_BUNDLE_TEST_KEY',
        'DYNAMODB_BUNDLE_TEST_SECRET',
    ];

    /** @var list<TestKernel> */
    private array $kernels = [];

    /** @var array<string, false|string> */
    private array $originalEnv = [];

    #[Override]
    protected function setUp(): void
    {
        foreach (self::ENV_VARIABLES as $name) {
            $this->originalEnv[$name] = getenv($name);
            putenv($name);
        }
    }

    /**
     * @throws IOException
     */
    #[Override]
    protected function tearDown(): void
    {
        $filesystem = new Filesystem();

        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
            $filesystem->remove($kernel->tempDir);
        }

        foreach ($this->originalEnv as $name => $value) {
            putenv(is_string($value) ? $name . '=' . $value : $name);
        }

        foreach (self::PLACEHOLDER_VARIABLES as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
        }
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testSignsRequestWithConfiguredCredentials(): void
    {
        $request = $this->sendListTables([
            'region' => 'eu-west-1',
            'credentials' => ['key' => 'AKIDCONFIGURED', 'secret' => 'configured-secret'],
        ]);

        self::assertSame('https://dynamodb.eu-west-1.amazonaws.com/', (string) $request->getUri());
        self::assertMatchesRegularExpression(
            '#Credential=AKIDCONFIGURED/\d{8}/eu-west-1/dynamodb/aws4_request#',
            $request->getHeaderLine('Authorization'),
        );
        self::assertFalse($request->hasHeader('X-Amz-Security-Token'));
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testSendsSessionTokenWhenConfigured(): void
    {
        $request = $this->sendListTables([
            'region' => 'eu-west-1',
            'credentials' => ['key' => 'AKIDCONFIGURED', 'secret' => 'configured-secret', 'token' => 'session-token'],
        ]);

        self::assertSame('session-token', $request->getHeaderLine('X-Amz-Security-Token'));
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testAcceptsExplicitNullToken(): void
    {
        $request = $this->sendListTables([
            'region' => 'eu-west-1',
            'credentials' => ['key' => 'AKIDCONFIGURED', 'secret' => 'configured-secret', 'token' => null],
        ]);

        self::assertFalse($request->hasHeader('X-Amz-Security-Token'));
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testResolvesEnvPlaceholdersAtRuntime(): void
    {
        $_SERVER['DYNAMODB_BUNDLE_TEST_REGION'] = $_ENV['DYNAMODB_BUNDLE_TEST_REGION'] = 'ap-south-1';
        $_SERVER['DYNAMODB_BUNDLE_TEST_KEY'] = $_ENV['DYNAMODB_BUNDLE_TEST_KEY'] = 'AKIDFROMPLACEHOLDER';
        $_SERVER['DYNAMODB_BUNDLE_TEST_SECRET'] = $_ENV['DYNAMODB_BUNDLE_TEST_SECRET'] = 'placeholder-secret';

        $request = $this->sendListTables([
            'region' => '%env(DYNAMODB_BUNDLE_TEST_REGION)%',
            'credentials' => [
                'key' => '%env(DYNAMODB_BUNDLE_TEST_KEY)%',
                'secret' => '%env(DYNAMODB_BUNDLE_TEST_SECRET)%',
            ],
        ]);

        self::assertSame('dynamodb.ap-south-1.amazonaws.com', $request->getUri()->getHost());
        self::assertMatchesRegularExpression(
            '#Credential=AKIDFROMPLACEHOLDER/\d{8}/ap-south-1/dynamodb/aws4_request#',
            $request->getHeaderLine('Authorization'),
        );
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testFallsBackToProcessEnvironmentWithoutCredentials(): void
    {
        putenv('AWS_ACCESS_KEY_ID=AKIDFROMENV');
        putenv('AWS_SECRET_ACCESS_KEY=env-secret');
        putenv('AWS_SESSION_TOKEN=env-token');

        $request = $this->sendListTables(['region' => 'us-east-2']);

        self::assertMatchesRegularExpression(
            '#Credential=AKIDFROMENV/\d{8}/us-east-2/dynamodb/aws4_request#',
            $request->getHeaderLine('Authorization'),
        );
        self::assertSame('env-token', $request->getHeaderLine('X-Amz-Security-Token'));
    }

    /**
     * @throws Exception
     */
    public function testThrowsWhenCredentialsAreMissingEverywhere(): void
    {
        $kernel = $this->bootKernel(['region' => 'us-east-2']);

        $this->expectException(MissingCredentialsException::class);

        $kernel->getContainer()->get(TestKernel::CLIENT_BY_INTERFACE);
    }

    /**
     * @throws Exception
     */
    public function testDiscoversHttpClientWhenContainerHasNone(): void
    {
        $kernel = $this->bootKernel(
            ['region' => 'eu-west-1', 'credentials' => ['key' => 'AKIDCONFIGURED', 'secret' => 'configured-secret']],
            withHttpClient: false,
        );

        self::assertInstanceOf(
            DynamoDBClientInterface::class,
            $kernel->getContainer()->get(TestKernel::CLIENT_BY_INTERFACE),
        );
    }

    /**
     * @throws Exception
     */
    public function testAutowiresClientByInterface(): void
    {
        $kernel = $this->bootKernel(
            ['region' => 'eu-west-1', 'credentials' => ['key' => 'AKIDCONFIGURED', 'secret' => 'configured-secret']],
            autowiredService: InterfaceConsumer::class,
        );
        $container = $kernel->getContainer();
        $container->set(ClientInterface::class, new MockClient());

        $consumer = $container->get(InterfaceConsumer::class);
        self::assertInstanceOf(InterfaceConsumer::class, $consumer);
        self::assertSame($container->get(TestKernel::CLIENT_BY_INTERFACE), $consumer->client);
    }

    /**
     * @throws Exception
     */
    public function testDoesNotAutowireClientByClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DynamoDBClient/');

        $this->bootKernel(
            ['region' => 'eu-west-1', 'credentials' => ['key' => 'AKIDCONFIGURED', 'secret' => 'configured-secret']],
            autowiredService: ClassConsumer::class,
        );
    }

    /**
     * @param array<string, mixed> $config
     * @throws Exception
     */
    #[DataProvider('provideRejectsInvalidConfigurationCases')]
    public function testRejectsInvalidConfiguration(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->bootKernel($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideRejectsInvalidConfigurationCases(): iterable
    {
        yield 'missing region' => [[]];

        yield 'empty region' => [['region' => '']];

        yield 'missing credentials secret' => [['region' => 'eu-west-1', 'credentials' => ['key' => 'AKID']]];

        yield 'missing credentials key' => [['region' => 'eu-west-1', 'credentials' => ['secret' => 'secret']]];

        yield 'empty credentials key' => [['region' => 'eu-west-1', 'credentials' => ['key' => '', 'secret' => 'secret']]];

        yield 'empty credentials secret' => [['region' => 'eu-west-1', 'credentials' => ['key' => 'AKID', 'secret' => '']]];

        yield 'empty credentials token' => [
            ['region' => 'eu-west-1', 'credentials' => ['key' => 'AKID', 'secret' => 'secret', 'token' => '']],
        ];

        yield 'unknown root key' => [['region' => 'eu-west-1', 'endpoint' => 'http://localhost:8000']];

        yield 'unknown credentials key' => [
            ['region' => 'eu-west-1', 'credentials' => ['key' => 'AKID', 'secret' => 'secret', 'profile' => 'default']],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @throws Exception
     * @throws ExceptionInterface
     */
    private function sendListTables(array $config): RequestInterface
    {
        $kernel = $this->bootKernel($config);
        $httpClient = new MockClient();
        $httpClient->addResponse(new Response(body: '{"TableNames":[]}'));

        $container = $kernel->getContainer();
        $container->set(ClientInterface::class, $httpClient);

        $client = $container->get(TestKernel::CLIENT_BY_INTERFACE);
        self::assertInstanceOf(DynamoDBClientInterface::class, $client);

        $client->listTables();

        $request = $httpClient->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    /**
     * @param array<string, mixed> $config
     * @param null|class-string $autowiredService
     * @throws Exception
     */
    private function bootKernel(
        array $config,
        bool $withHttpClient = true,
        ?string $autowiredService = null,
    ): TestKernel {
        $kernel = new TestKernel($config, $withHttpClient, $autowiredService);
        $this->kernels[] = $kernel;
        $kernel->boot();

        return $kernel;
    }
}
