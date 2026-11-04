<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory;

use Contenir\Extension\CampaignMonitor\Service\Api;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * An API client over a fake HTTP client, signing in with a test key.
 */
final class Apis
{
    public const string CLIENT = 'c9d8e7f6';

    public const string KEY = 'test-api-key';

    public static function over(FakeHttpClient $http): Api
    {
        $factory = new Psr17Factory();

        return new Api(new Connection(self::KEY, self::CLIENT), $http, $factory, $factory);
    }
}
