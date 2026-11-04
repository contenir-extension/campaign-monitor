<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Operation\Contribution;
use Contenir\Extension\Api\Operation\Operation;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Api\Settings\SettingsSection;
use Contenir\Extension\CampaignMonitor\Field\ClientField;
use Contenir\Extension\CampaignMonitor\Handler\BindingsScreen;
use Contenir\Extension\CampaignMonitor\Handler\SyncScreen;
use Contenir\Extension\CampaignMonitor\Operation\RegisterWebhooks;
use Contenir\Extension\CampaignMonitor\Operation\SyncNow;
use Contenir\Extension\CampaignMonitor\Operation\TestConnection;

use function sprintf;

/**
 * The add-on's settings section, Campaign Monitor under Integration: the API key, kept in this
 * environment's local settings, the client whose lists are kept, and the secret in the webhook's
 * address, with Test connection, Sync all now and Re-register webhooks.
 *
 * @api
 */
final class CampaignMonitorSection
{
    public const string NAME = 'campaign-monitor';

    /**
     * @mago-expect lint:no-literal-password The field's name, not a secret.
     */
    public const string API_KEY = 'api_key';

    public const string CLIENT = 'client_id';

    /**
     * @mago-expect lint:no-literal-password The field's name, not a secret.
     */
    public const string WEBHOOK_SECRET = 'webhook_secret';

    /**
     * The section, with its field type named for the add-on's handle.
     */
    public static function define(Handle $handle): SettingsSection
    {
        return new SettingsSection(
            name: self::NAME,
            title: 'Campaign Monitor',
            category: SettingsCategory::Integration,
            form: new Form(
                [
                    Field::password(
                        name: self::API_KEY,
                        label: 'API key',
                        description: 'From Campaign Monitor under Account settings > API keys. Kept in this'
                            . ' environment\'s local settings.',
                    ),
                    new Field(
                        name: self::CLIENT,
                        label: 'Client',
                        type: $handle->fieldType(ClientField::NAME),
                        description: 'The client whose lists the site keeps. Save the API key first to choose one.',
                    ),
                    Field::password(
                        name: self::WEBHOOK_SECRET,
                        label: 'Webhook secret',
                        description: 'Made by Re-register webhooks, and part of the address Campaign Monitor calls.'
                            . ' Clear it and re-register to change it.',
                    ),
                ],
                [
                    new Group('connection', 'Connection', [self::API_KEY, self::CLIENT]),
                    new Group('webhook', 'Webhook', [self::WEBHOOK_SECRET]),
                ],
            ),
            operations: [
                new Operation(
                    name: TestConnection::NAME,
                    label: 'Test connection',
                    icon: 'plug-connected',
                    privilege: 'update',
                    handler: TestConnection::class,
                    surface: 'view',
                ),
                new Operation(
                    name: SyncNow::NAME,
                    label: 'Sync all now',
                    icon: 'refresh',
                    privilege: 'update',
                    handler: SyncNow::class,
                    surface: 'view',
                ),
                new Operation(
                    name: RegisterWebhooks::NAME,
                    label: 'Re-register webhooks',
                    icon: 'webhook',
                    privilege: 'update',
                    handler: RegisterWebhooks::class,
                    surface: 'view',
                    confirm: true,
                    confirmMessage: 'Register each list\'s webhook again? Campaign Monitor then calls the new address,'
                        . ' and the old one stops.',
                ),
            ],
            contributions: [
                new Contribution(
                    region: 'layout-nav',
                    type: 'link',
                    label: 'Sync',
                    icon: 'refresh',
                    url: sprintf('/admin/extension/%s/%s', $handle->value, SyncScreen::NAME),
                ),
                new Contribution(
                    region: 'layout-nav',
                    type: 'link',
                    label: 'Bindings',
                    icon: 'list',
                    url: sprintf('/admin/extension/%s/%s', $handle->value, BindingsScreen::NAME),
                ),
            ],
        );
    }
}
