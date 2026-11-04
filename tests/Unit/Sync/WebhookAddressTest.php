<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Sync;

use Contenir\Extension\Api\Settings\SiteSettingsInterface;
use Contenir\Extension\Api\Testing\MemorySiteSettings;
use Contenir\Extension\CampaignMonitor\Sync\WebhookAddress;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[Group('unit')]
final class WebhookAddressTest extends TestCase
{
    #[Test]
    public function hasNoAddressWhenTheHostLendsNoSiteSettings(): void
    {
        static::assertNull(WebhookAddress::for(
            Contexts::make(services: [SiteSettingsInterface::class => new stdClass()]),
            'newsletter',
            'k',
        ));
    }

    #[Test]
    public function hasNoAddressWhenTheSitesAddressIsNotSet(): void
    {
        static::assertNull(WebhookAddress::for(
            Contexts::make(services: [SiteSettingsInterface::class => new MemorySiteSettings()]),
            'newsletter',
            'k',
        ));
    }

    #[Test]
    public function isTheEventsWebhookOnTheSitesDomainWithTheBindingAndKey(): void
    {
        static::assertSame(
            'https://www.example.com/api/extension/campaign-monitor/events?binding=new+letter&key=k%26y',
            WebhookAddress::for(Contexts::make(), 'new letter', 'k&y'),
        );
    }
}
