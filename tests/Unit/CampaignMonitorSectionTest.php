<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Group as FormGroup;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Operation\Contribution;
use Contenir\Extension\Api\Operation\Operation;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_map;

#[Group('unit')]
final class CampaignMonitorSectionTest extends TestCase
{
    #[Test]
    public function asksForTheKeyClientAndWebhookSecret(): void
    {
        static::assertSame(
            [
                ['api_key',        'password',                true],
                ['client_id',      'campaign-monitor:client', false],
                ['webhook_secret', 'password',                true],
            ],
            array_map(
                static fn(Field $field): array => [$field->name, $field->type, $field->local],
                CampaignMonitorSection::define(new Handle('campaign-monitor'))->form->fields,
            ),
        );
    }

    #[Test]
    public function confirmsBeforeRegisteringWebhooksAgain(): void
    {
        static::assertSame(
            ['test-connection' => false, 'sync-now' => false, 'register-webhooks' => true],
            array_column(
                array_map(
                    static fn(Operation $operation): array => [$operation->name, $operation->confirm],
                    CampaignMonitorSection::define(new Handle('campaign-monitor'))->operations,
                ),
                1,
                0,
            ),
        );
    }

    #[Test]
    public function groupsTheConnectionApartFromTheWebhook(): void
    {
        static::assertSame(
            [['connection', 'Connection', [['api_key'], ['client_id']]], ['webhook', 'Webhook', [['webhook_secret']]]],
            array_map(
                static fn(FormGroup $group): array => [$group->id, $group->legend, $group->rows],
                CampaignMonitorSection::define(new Handle('campaign-monitor'))->form->groups,
            ),
        );
    }

    #[Test]
    public function linksToTheSyncAndBindingsScreens(): void
    {
        static::assertSame(
            ['/admin/extension/campaign-monitor/sync', '/admin/extension/campaign-monitor/bindings'],
            array_map(
                static fn(Contribution $contribution): string => $contribution->url,
                CampaignMonitorSection::define(new Handle('campaign-monitor'))->contributions,
            ),
        );
    }

    #[Test]
    public function saysWhereEachValueComesFrom(): void
    {
        static::assertSame(
            [
                'From Campaign Monitor under Account settings > API keys. Kept in this environment\'s local settings.',
                'The client whose lists the site keeps. Save the API key first to choose one.',
                'Made by Re-register webhooks, and part of the address Campaign Monitor calls. Clear it and'
                    . ' re-register to change it.',
            ],
            array_map(
                static fn(Field $field): string => $field->description,
                CampaignMonitorSection::define(new Handle('campaign-monitor'))->form->fields,
            ),
        );
    }

    #[Test]
    public function sitsUnderIntegrationAsCampaignMonitor(): void
    {
        $section = CampaignMonitorSection::define(new Handle('campaign-monitor'));

        static::assertSame(
            ['campaign-monitor', 'Campaign Monitor', SettingsCategory::Integration],
            [$section->name, $section->title, $section->category],
        );
    }

    #[Test]
    public function warnsThatReRegisteringMovesTheWebhook(): void
    {
        static::assertSame(
            'Register each list\'s webhook again? Campaign Monitor then calls the new address, and the old one stops.',
            CampaignMonitorSection::define(new Handle('campaign-monitor'))->operations[2]->confirmMessage,
        );
    }
}
