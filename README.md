<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset=".github/assets/logo-dark.svg">
    <img src=".github/assets/logo-light.svg" alt="dynamodb-client-bundle" width="480">
  </picture>
</p>

<p align="center">
  <a href="https://github.com/imper86/dynamodb-client-bundle/actions/workflows/ci.yml"><img src="https://github.com/imper86/dynamodb-client-bundle/actions/workflows/ci.yml/badge.svg?branch=main" alt="CI"></a>
  <a href="https://codecov.io/gh/imper86/dynamodb-client-bundle"><img src="https://codecov.io/gh/imper86/dynamodb-client-bundle/graph/badge.svg" alt="Coverage"></a>
  <a href="https://packagist.org/packages/imper86/dynamodb-client-bundle"><img src="https://img.shields.io/packagist/v/imper86/dynamodb-client-bundle" alt="Packagist"></a>
  <a href="LICENSE"><img src="https://img.shields.io/packagist/l/imper86/dynamodb-client-bundle" alt="License"></a>
</p>

A Symfony bundle that registers [`imper86/dynamodb-client`](https://github.com/imper86/dynamodb-client)
as an autowirable service. It works with Symfony 6.4, 7.4 and 8.x on PHP 8.4 or newer.

## Installation

```bash
composer require imper86/dynamodb-client-bundle
```

The client needs a PSR-18 HTTP client and PSR-17 factories. If your app has none yet, install one,
for example:

```bash
composer require symfony/http-client nyholm/psr7
```

There is no Flex recipe, so register the bundle yourself in `config/bundles.php`:

```php
return [
    // ...
    Imper86\DynamoDBClientBundle\DynamoDBClientBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/imper86_dynamodb_client.yaml
imper86_dynamodb_client:
    region: '%env(AWS_REGION)%'                 # required, non-empty
    credentials:                                # optional; omit to use the process env (getenv)
        key: '%env(AWS_ACCESS_KEY_ID)%'         # required inside credentials
        secret: '%env(AWS_SECRET_ACCESS_KEY)%'  # required inside credentials
        token: ~                                # optional session token
```

Run `bin/console config:dump-reference imper86_dynamodb_client` to see the full reference.

If the container has a `Psr\Http\Client\ClientInterface` service (for example from
`symfony/http-client` with `nyholm/psr7` installed), the client sends its requests through it, so they
show up in the profiler. Otherwise it finds an HTTP client through
[`php-http/discovery`](https://github.com/php-http/discovery).

### Credentials from `.env`

Without a `credentials` section, the client reads `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` and
`AWS_SESSION_TOKEN` with `getenv()` when the service is created. It sees only real process
environment variables. Values that exist only in your `.env` files are **not** visible to it, because
Symfony's Dotenv does not call `putenv()` by default. If the variables are missing, fetching the
client throws a `MissingCredentialsException`.

If your credentials live in `.env`, pass them in explicitly:

```yaml
imper86_dynamodb_client:
    region: '%env(AWS_REGION)%'
    credentials:
        key: '%env(AWS_ACCESS_KEY_ID)%'
        secret: '%env(AWS_SECRET_ACCESS_KEY)%'
```

## Usage

Type your dependencies against `DynamoDBClientInterface`. It is the only autowirable type; the
concrete `DynamoDBClient` class is not.

```php
use Imper86\DynamoDBClient\DynamoDBClientInterface;
use Imper86\DynamoDBClient\ValueObject\StringList;

final readonly class TableLister
{
    public function __construct(
        private DynamoDBClientInterface $dynamoDB,
    ) {}

    public function list(): StringList
    {
        return $this->dynamoDB->listTables()->tableNames;
    }
}
```

See the [library README](https://github.com/imper86/dynamodb-client#readme) for the operations, request
and response objects, and error handling.

## License

MIT
