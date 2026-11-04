<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Check;

use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\CampaignMonitor\Check\ConnectionCheck;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConnectionCheckTest extends TestCase
{
    #[Test]
    public function failsWithAWayToTheSettingsWhileTheKeyIsRefused(): void
    {
        $result = (new ConnectionCheck(Http::connections(new FakeHttpClient(Replies::refused()))))->check(
            Contexts::make(Settings::connected()),
        );

        static::assertSame(
            [
                Status::Failure,
                'The service refused the connection: Must supply a valid HTTP Basic Authorization header Nothing is'
                    . ' sent until it\'s put right; everything waits.',
                'Open the connection settings',
                'Check the API key and client in Site Settings > Integration > Campaign Monitor, save, and press Test'
                    . ' connection. Sending starts again in the next minute.',
            ],
            [
                $result->status,
                $result->message,
                $result->fix instanceof GuidedFix ? $result->fix->label : null,
                $result->fix instanceof GuidedFix ? $result->fix->explanation : null,
            ],
        );
    }

    #[Test]
    public function isLabelledForTheUpdateCheck(): void
    {
        static::assertSame('Campaign Monitor connection', (new ConnectionCheck())->label());
    }

    #[Test]
    public function namesTheClientItReaches(): void
    {
        $result = (new ConnectionCheck(Http::connections(new FakeHttpClient(Replies::client()))))->check(
            Contexts::make(Settings::connected()),
        );

        static::assertSame([Status::Success, 'Connected to Studio Esteta.'], [$result->status, $result->message]);
    }

    #[Test]
    public function warnsUntilTheConnectionIsSaved(): void
    {
        $result = (new ConnectionCheck())->check(Contexts::make(Settings::keyOnly()));

        static::assertSame(
            [Status::Warning, 'Choose the client, and save, first.'],
            [$result->status, $result->message],
        );
    }
}
