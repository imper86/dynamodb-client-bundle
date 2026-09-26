<?php

declare(strict_types=1);

namespace Imper86\DynamoDBClientBundleTests\Fixtures;

use Imper86\DynamoDBClient\DynamoDBClientInterface;

final readonly class InterfaceConsumer
{
    public function __construct(
        public DynamoDBClientInterface $client,
    ) {}
}
