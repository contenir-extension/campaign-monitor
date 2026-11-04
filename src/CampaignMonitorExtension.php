<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor;

use Contenir\Extension\Api\Event\Record\RecordPostDelete;
use Contenir\Extension\Api\Event\Record\RecordPostSave;
use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Api\Handler\Privilege;
use Contenir\Extension\Api\Listener\Delivery;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Api\Schedule\Frequency;
use Contenir\Extension\CampaignMonitor\Check\ConnectionCheck;
use Contenir\Extension\CampaignMonitor\Check\OutboxCheck;
use Contenir\Extension\CampaignMonitor\Check\SetupCheck;
use Contenir\Extension\CampaignMonitor\Check\WebhookCheck;
use Contenir\Extension\CampaignMonitor\Field\ClientField;
use Contenir\Extension\CampaignMonitor\Handler\BindingsScreen;
use Contenir\Extension\CampaignMonitor\Handler\SyncScreen;
use Contenir\Extension\CampaignMonitor\Listener\RemoveRecord;
use Contenir\Extension\CampaignMonitor\Listener\SyncRecord;
use Contenir\Extension\CampaignMonitor\Schedule\Drain;
use Contenir\Extension\CampaignMonitor\Sync\WebhookAddress;
use Contenir\Extension\CampaignMonitor\Webhook\EventsWebhook;
use Override;

/**
 * The Campaign Monitor add-on: keeps a client's lists in step with the site's sign-ups and the
 * records a site binds, through an outbox drained every minute, and hears back about
 * unsubscribes, bounces and complaints through its webhook.
 *
 * @api
 */
final class CampaignMonitorExtension implements ExtensionInterface
{
    /**
     * The add-on's table prefix: its handle, with the dash a table name can't hold as an
     * underscore.
     */
    public const string PREFIX = 'ext_campaign_monitor_';

    /**
     * The namespace of the add-on's migrations, in src/Schema.
     */
    public const string SCHEMA = __NAMESPACE__ . '\\Schema';

    #[Override]
    public function register(Registry $registry): void
    {
        $registry->fieldType(ClientField::NAME, ClientField::class);
        $registry->settingsSection(CampaignMonitorSection::define($registry->handle()));
        $registry->listen(SyncRecord::NAME, RecordPostSave::class, SyncRecord::class, delivery: Delivery::Immediate);
        $registry->listen(
            RemoveRecord::NAME,
            RecordPostDelete::class,
            RemoveRecord::class,
            delivery: Delivery::Immediate,
        );
        $registry->schedule(Drain::NAME, Drain::class, Frequency::everyMinutes(1));
        $registry->webhook(WebhookAddress::NAME, EventsWebhook::class);
        $registry->migrations(__DIR__ . '/Schema', self::SCHEMA);
        $registry->handler(SyncScreen::NAME, SyncScreen::class);
        $registry->handler(BindingsScreen::NAME, BindingsScreen::class, Privilege::View);
        $registry->diagnostic(SetupCheck::NAME, SetupCheck::class);
        $registry->diagnostic(ConnectionCheck::NAME, ConnectionCheck::class);
        $registry->diagnostic(OutboxCheck::NAME, OutboxCheck::class);
        $registry->diagnostic(WebhookCheck::NAME, WebhookCheck::class);
    }
}
