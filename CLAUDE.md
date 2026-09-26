# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

A Symfony bundle (`^6.4 || ^7.4 || ^8.0`, PHP >= 8.4) that registers `imper86/dynamodb-client` as a
service. The library lives at `../oo-aws/dynamodb-client` and has its own CLAUDE.md, which is the
authority on everything the client itself does.

## Commands

```bash
composer analyse   # cs:check + stan + rector:check + cda + unit — run before every commit
composer fix       # cs:fix + rector:fix — run it twice, then analyse (see gotchas)
composer unit      # phpunit
vendor/bin/phpunit --filter testName
```

`composer analyse` also runs as a captainhook pre-commit action, so any violation fails the commit.

CI runs `composer analyse` on the newest dependencies. It runs PHPUnit alone on `--prefer-lowest` and
with every symfony/* package pinned to `~6.4.33` and to `~7.4.0`. The lock file is not committed.
When lowering a dependency floor, check it with `composer update --prefer-lowest` locally.

## Architecture

The bundle is a thin wiring layer. `DynamoDBClientBundle` (an `AbstractBundle`) is the whole
production code: `configure()` defines the config tree and `loadExtension()` registers the services.
There is no separate Extension/Configuration class and no YAML/XML service file. XML service config is
removed in Symfony 8, so keep all wiring in PHP.

- The config root key is `imper86_dynamodb_client`. The tree holds only `region` (required) and
  `credentials` (optional: `key`, `secret`, `token`). Add more only on request.
- Service `imper86_dynamodb_client.client` (private) is `DynamoDBClient`. Only
  `DynamoDBClientInterface` is aliased to it for autowiring; the concrete `DynamoDBClient` class is
  deliberately not autowirable, so apps depend on the interface. New services follow the same pattern:
  a snake_case id prefixed `imper86_dynamodb_client.` plus an alias for their interface.
- `$credentials` is an `inline_service(Credentials::class)` when configured, otherwise `null`. The
  library then falls back to `Credentials::fromEnvironment()`.
- `$httpClient` is `service(ClientInterface::class)->nullOnInvalid()`. It uses the app's PSR-18 client
  when there is one (so requests appear in the profiler) and otherwise falls back to discovery. There
  is no config key for it.
- Never inject the app's `serializer`. The library needs its own `SerializerFactory` setup
  (PascalCase names, TimestampNormalizer, CollectionNormalizer, …). Leave the PSR-17 factories to
  discovery as well unless there is a concrete reason.

## Adding a feature

- **No DynamoDB logic here.** No request/response classes, no decorators, no helpers around the
  client. If a feature needs new client behaviour, add it to the library first. Then raise the
  library floor here and only wire it.
- **Every config option maps to a library constructor argument.** Don't add options the library
  cannot honour. Keep the tree minimal and give every new option a default, so existing configs stay
  valid (BC).
- **Config values may be `%env()%` placeholders.** Validate them only through the config tree
  (`isRequired`, `cannotBeEmpty`). Don't inspect, parse or build value objects from them in
  `loadExtension()`. Anything the library validates in its constructor (such as `Credentials`) goes
  in as an `inline_service()`, so it is built at runtime from resolved values.
- **Use only APIs that exist in Symfony 6.4 and are not deprecated in 7.4 or 8.x.** PHPStan's
  deprecation rules only see the newest Symfony, so run the 6.4-pinned suite before relying on a
  newer API.
- Update the README's config example whenever the tree changes.

## Tests

- Integration first: boot `tests/Fixtures/TestKernel` with a config array, fetch the client through a
  public test alias, and assert on the **signed request** that reaches a `Http\Mock\Client` registered
  as the synthetic `ClientInterface` service (host `dynamodb.<region>.amazonaws.com`, `Credential=` in
  `Authorization`, `X-Amz-Security-Token`). Don't assert on definitions or private container state.
- Each kernel gets its own temp cache dir. The container is cached, so two configs sharing a dir will
  quietly reuse the first one.
- Every config rule gets a case in `testRejectsInvalidConfiguration`'s data provider, which expects
  `InvalidConfigurationException`.
- `setUp` clears `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY`/`AWS_SESSION_TOKEN`, so the developer's own
  AWS env never leaks into the fallback tests.
- Tests that touch `putenv()`, `$_SERVER` or `$_ENV` restore them in `tearDown`.
- `#[CoversClass]` on every test class (php-cs-fixer enforces it).

## Conventions and gotchas

- **PHPStan runs at level 10** with strict rules, `checkImplicitMixed` and
  `missingCheckedExceptionInThrows`. Every method that can throw a checked exception needs an accurate
  `@throws`, tests included. Don't silence findings with baselines, `@phpstan-ignore`, casts or
  widened types.
- **The fluent config builder is weakly typed.** Build the tree through local variables instead of
  long `->end()` chains, and narrow `rootNode()` (which returns `NodeDefinition|ArrayNodeDefinition`)
  with `instanceof`. Narrow the processed `array $config` with explicit checks before using it.
- **`phpstan-phpunit` narrows aggressively.** After an assertion PHPStan may consider a subject
  non-null and flag later `?->` as `nullsafe.neverNull`. Assign to a local, `assertInstanceOf`, and
  use plain `->` from there.
- Classes are `final`, and also `readonly` where the parent allows it. The bundle class can't be,
  because `AbstractBundle` has mutable state. Call multi-argument constructors with named arguments.
- php-cs-fixer enforces `@Symfony` + `@PER-CS2.0` plus global namespace imports, so `use function`
  every global function and keep imports sorted (`composer fix` does it).
- Rector runs a php85 target with wide prepared sets (including `symfonyConfigs`). Check `rector.php`
  before fighting one of its rules.
- **`composer fix` can need two runs.** php-cs-fixer runs *before* Rector, so a Rector rewrite can
  leave a file that `cs:check` then rejects.
- **Never `assertEquals` two objects.** Rector turns it into `assertSame`, which compares identity.
  Compare members instead.
- **`cannotBeEmpty()` also rejects an explicit `~`.** Use it only on required nodes. Optional
  nullable nodes (like `token`) reject `''` through `validate()->ifTrue()` instead, so `token: ~` stays
  valid.
- **CDA can't see `inline_service()`/`service()`** (they are declared inside `ContainerConfigurator.php`),
  so `composer-dependency-analyser.php` ignores them by name. Add any new configurator function there.
- `TestKernel::registerBundles()` narrows its `@return` to `list<DynamoDBClientBundle>`: the inherited
  `iterable<BundleInterface>` names an interface deprecated in Symfony 8.1, which PHPStan flags.
- **Credentials fallback:** with no `credentials` configured, the library calls `getenv()` **when the
  service is created**. Values that exist only in `.env` are invisible to it (Dotenv doesn't
  `putenv()` by default). A missing variable throws `MissingCredentialsException` when the client is
  first fetched, not when a request is sent. `.env` users should configure
  `credentials: { key: '%env(...)%', secret: '%env(...)%' }` explicitly.
