<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Handler;

use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\CampaignMonitor\Handler\BindingsScreen;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\People;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\SyncKit;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BindingsScreenTest extends TestCase
{
    private const string URL = '/admin/extension/campaign-monitor/bindings';

    #[Test]
    public function listsEachBindingWithExactlyWhatItSends(): void
    {
        $kit = new SyncKit([
            ...SyncKit::NEWSLETTER,
            'members' => [
                'list'      => 'members-list',
                'source'    => 'library.members.member',
                'email'     => 'work_email',
                'fields'    => ['Source' => ['value' => 'CMS'], 'Mobile' => 'mobile'],
                'on_delete' => 'keep',
            ],
        ]);
        $kit->webhooks->save(new RegisteredWebhook('newsletter', 'w1', null, $kit->clock->now()));

        $result = (new BindingsScreen($kit->factory))->handle(new ServerRequest('GET', self::URL), Contexts::make());

        static::assertSame(
            [
                [
                    'name'    => 'newsletter',
                    'list'    => People::LIST,
                    'source'  => 'The site\'s sign-up forms',
                    'fields'  => 'email ← email, Name ← name',
                    'consent' => 'Signing up to the newsletter',
                    'delete'  => 'unsubscribe',
                    'webhook' => 'Registered 5 Nov 2026 09:00',
                ],
                [
                    'name'    => 'members',
                    'list'    => 'members-list',
                    'source'  => 'library.members.member',
                    'fields'  => 'email ← work_email, Source ← "CMS", Mobile ← mobile',
                    'consent' => null,
                    'delete'  => 'keep',
                    'webhook' => 'Not registered',
                ],
            ],
            $result->screen?->table?->rows,
        );
    }

    #[Test]
    public function saysWhatTheSiteStillNeeds(): void
    {
        $result = (new BindingsScreen(FakeSyncFactory::notReady('No pepper.')))->handle(
            new ServerRequest('GET', self::URL),
            Contexts::make(),
        );

        static::assertSame(['Bindings', 'No pepper.'], [$result->screen?->title, $result->screen?->intro]);
    }

    #[Test]
    public function saysWhereTheBindingsAreSet(): void
    {
        $result = (new BindingsScreen((new SyncKit())->factory))->handle(
            new ServerRequest('GET', self::URL),
            Contexts::make(),
        );

        static::assertSame(
            'Which list each sign-up form or section feeds, and exactly what it sends. They are set in the site\'s'
                . ' config/autoload/extensions/campaign-monitor.global.php.',
            $result->screen?->intro,
        );
    }
}
