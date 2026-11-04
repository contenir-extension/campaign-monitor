<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory;

use Contenir\Extension\CampaignMonitor\CampaignMonitorSection;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Settings\ArraySettings;
use SensitiveParameter;

/**
 * The section's settings at each stage of setting the add-on up.
 */
final class Settings
{
    /**
     * @mago-expect lint:no-literal-password A webhook secret made up for the tests.
     */
    public const string SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public static function connected(
        #[SensitiveParameter]
        ?string $secret = self::SECRET,
    ): ArraySettings {
        return new ArraySettings([
            CampaignMonitorSection::API_KEY        => Apis::KEY,
            CampaignMonitorSection::CLIENT         => Apis::CLIENT,
            CampaignMonitorSection::WEBHOOK_SECRET => $secret,
        ]);
    }

    public static function keyOnly(): ArraySettings
    {
        return new ArraySettings([CampaignMonitorSection::API_KEY => Apis::KEY]);
    }
}
