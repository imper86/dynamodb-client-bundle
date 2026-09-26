<?php

declare(strict_types=1);

namespace Imper86\DynamoDBClientBundle;

use Imper86\DynamoDBClient\DynamoDBClient;
use Imper86\DynamoDBClient\DynamoDBClientInterface;
use Imper86\DynamoDBClient\Model\Credentials;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function is_array;

final class DynamoDBClientBundle extends AbstractBundle
{
    private const string CLIENT_SERVICE_ID = 'imper86_dynamodb_client.client';

    protected string $extensionAlias = 'imper86_dynamodb_client';

    /**
     * @throws LogicException
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $rootNode = $definition->rootNode();

        if (!$rootNode instanceof ArrayNodeDefinition) {
            throw new LogicException('The root node of the configuration tree must be an array node.');
        }

        $children = $rootNode->children();

        $children->scalarNode('region')
            ->info('AWS region of the DynamoDB endpoint, for example "eu-central-1".')
            ->defaultValue('%env(AWS_REGION)%')
            ->cannotBeEmpty()
        ;

        $credentials = $children->arrayNode('credentials')
            ->info('AWS credentials. Each value defaults to its standard AWS environment variable.')
            ->addDefaultsIfNotSet()
        ;

        $credentialsChildren = $credentials->children();

        $credentialsChildren->scalarNode('key')
            ->info('AWS access key ID.')
            ->defaultValue('%env(AWS_ACCESS_KEY_ID)%')
            ->cannotBeEmpty()
        ;

        $credentialsChildren->scalarNode('secret')
            ->info('AWS secret access key.')
            ->defaultValue('%env(AWS_SECRET_ACCESS_KEY)%')
            ->cannotBeEmpty()
        ;

        $credentialsChildren->scalarNode('token')
            ->info('Session token of temporary credentials. An empty string means no token.')
            ->defaultValue('%env(default::AWS_SESSION_TOKEN)%')
            ->beforeNormalization()
            ->ifTrue(static fn(mixed $token): bool => '' === $token)
            ->then(static fn(): null => null)
        ;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $credentials = null;

        if (isset($config['credentials']) && is_array($config['credentials'])) {
            $credentials = inline_service(Credentials::class)
                ->args([
                    $config['credentials']['key'] ?? null,
                    $config['credentials']['secret'] ?? null,
                    $config['credentials']['token'] ?? null,
                ])
            ;
        }

        $services = $container->services();

        $services->set(self::CLIENT_SERVICE_ID, DynamoDBClient::class)
            ->private()
            ->args([
                '$region' => $config['region'] ?? null,
                '$credentials' => $credentials,
                '$httpClient' => service(ClientInterface::class)->nullOnInvalid(),
            ])
        ;

        $services->alias(DynamoDBClientInterface::class, self::CLIENT_SERVICE_ID);
    }
}
