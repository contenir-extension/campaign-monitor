<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Field;

use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\CampaignMonitor\Field\ClientField;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ClientFieldTest extends TestCase
{
    #[Test]
    public function acceptsAnyChoice(): void
    {
        static::assertSame([], (new ClientField())->validate('anything'));
    }

    #[Test]
    public function isAPlainSelect(): void
    {
        $field = new ClientField();

        static::assertSame([FieldType::Select, null], [$field->control(), $field->component()]);
    }

    #[Test]
    public function offersNothingBeforeAKeyIsSaved(): void
    {
        $http = new FakeHttpClient();

        static::assertSame(
            [[], []],
            [(new ClientField(Http::connections($http)))->options(Contexts::make()), $http->requests],
        );
    }

    #[Test]
    public function offersNothingWhileCampaignMonitorRefusesTheKey(): void
    {
        $field = new ClientField(Http::connections(new FakeHttpClient(Replies::refused())));

        static::assertSame([], $field->options(Contexts::make(Settings::keyOnly())));
    }

    #[Test]
    public function offersTheClientsTheKeyReaches(): void
    {
        $field = new ClientField(Http::connections(new FakeHttpClient(Replies::clients([
            'c1' => 'Studio Esteta',
            'c2' => 'Sector7g',
        ]))));

        static::assertSame(
            ['c1' => 'Studio Esteta', 'c2' => 'Sector7g'],
            $field->options(Contexts::make(Settings::keyOnly())),
        );
    }

    #[Test]
    public function storesTheChoiceTrimmed(): void
    {
        static::assertSame(['c1', ''], [(new ClientField())->normalise(' c1 '), (new ClientField())->normalise(null)]);
    }
}
