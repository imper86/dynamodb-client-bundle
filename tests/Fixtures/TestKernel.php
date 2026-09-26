<?php

declare(strict_types=1);

namespace Imper86\DynamoDBClientBundleTests\Fixtures;

use Imper86\DynamoDBClient\DynamoDBClientInterface;
use Imper86\DynamoDBClientBundle\DynamoDBClientBundle;
use Exception;
use Override;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

use function sys_get_temp_dir;
use function uniqid;

final class TestKernel extends Kernel
{
    public const string CLIENT_BY_INTERFACE = 'test.dynamodb_client.interface';

    public readonly string $tempDir;

    /**
     * @param array<string, mixed> $bundleConfig config of the imper86_dynamodb_client extension
     * @param bool $withHttpClient whether to register a synthetic PSR-18 ClientInterface service
     * @param null|class-string $autowiredService class registered as a public, autowired service
     */
    public function __construct(
        private readonly array $bundleConfig,
        private readonly bool $withHttpClient = true,
        private readonly ?string $autowiredService = null,
    ) {
        parent::__construct('test', false);

        $this->tempDir = sys_get_temp_dir() . '/imper86_dynamodb_client_bundle_' . uniqid('', true);
    }

    /**
     * @return list<DynamoDBClientBundle>
     */
    public function registerBundles(): iterable
    {
        return [new DynamoDBClientBundle()];
    }

    /**
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('imper86_dynamodb_client', $this->bundleConfig);

            if ($this->withHttpClient) {
                $container->register(ClientInterface::class)
                    ->setSynthetic(true)
                    ->setPublic(true)
                ;
            }

            $container->setAlias(self::CLIENT_BY_INTERFACE, DynamoDBClientInterface::class)->setPublic(true);

            if (null !== $this->autowiredService) {
                $container->autowire($this->autowiredService)->setPublic(true);
            }
        });
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->tempDir . '/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->tempDir . '/log';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }
}
