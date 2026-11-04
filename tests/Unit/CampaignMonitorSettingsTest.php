<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit;

use Contenir\Extension\CampaignMonitor\CampaignMonitorSection;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSettings;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Settings\ArraySettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CampaignMonitorSettingsTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string|null}>
     */
    public static function missingProvider(): array
    {
        return [
            'nothing'       => [[], 'Enter the API key, and save, first.'],
            'a blank key'   => [[CampaignMonitorSection::API_KEY => '  '], 'Enter the API key, and save, first.'],
            'no client'     => [[CampaignMonitorSection::API_KEY => 'k'], 'Choose the client, and save, first.'],
            'a key and one' => [[CampaignMonitorSection::API_KEY => 'k', CampaignMonitorSection::CLIENT => 'c'], null],
        ];
    }

    #[Test]
    public function connectsWithTheKeyAndClient(): void
    {
        $settings = CampaignMonitorSettings::from(new ArraySettings([
            CampaignMonitorSection::API_KEY => ' ' . Apis::KEY . ' ',
            CampaignMonitorSection::CLIENT  => Apis::CLIENT,
        ]));

        static::assertEquals(new Connection(Apis::KEY, Apis::CLIENT), $settings->connection());
    }

    #[Test]
    public function hasNoConnectionWithoutAClient(): void
    {
        static::assertNull(
            CampaignMonitorSettings::from(new ArraySettings([
                CampaignMonitorSection::API_KEY => Apis::KEY,
            ]))->connection(),
        );
    }

    #[Test]
    public function hasNoConnectionWithoutAKey(): void
    {
        static::assertNull(
            CampaignMonitorSettings::from(new ArraySettings([
                CampaignMonitorSection::CLIENT => Apis::CLIENT,
            ]))->connection(),
        );
    }

    #[Test]
    public function readsAValueThatIsNotTextAsNotSet(): void
    {
        static::assertNull(CampaignMonitorSettings::from(new ArraySettings([
            CampaignMonitorSection::API_KEY => 12,
        ]))->apiKey);
    }

    #[Test]
    public function readsTheWebhookSecret(): void
    {
        static::assertSame(
            's3cret',
            CampaignMonitorSettings::from(new ArraySettings([
                CampaignMonitorSection::WEBHOOK_SECRET => 's3cret',
            ]))->webhookSecret,
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('missingProvider')]
    #[Test]
    public function saysWhatIsStillToEnter(array $values, ?string $missing): void
    {
        static::assertSame($missing, CampaignMonitorSettings::from(new ArraySettings($values))->missing());
    }
}
