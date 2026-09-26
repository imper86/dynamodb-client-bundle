<?php

declare(strict_types=1);

namespace Imper86\DynamoDBClientBundleTests\Fixtures;

use Imper86\DynamoDBClient\DynamoDBClient;

final class ClassConsumer
{
    public function __construct(
        public readonly DynamoDBClient $client,
    ) {}
}
