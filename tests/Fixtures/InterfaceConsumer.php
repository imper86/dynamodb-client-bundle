<?php

declare(strict_types=1);

namespace Imper86\DynamoDBClientBundleTests\Fixtures;

use Imper86\DynamoDBClient\DynamoDBClientInterface;

final class InterfaceConsumer
{
    public function __construct(
        public readonly DynamoDBClientInterface $client,
    ) {}
}
