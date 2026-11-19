<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Listener;

use Contenir\Extension\Api\Event\Change;
use Contenir\Extension\Api\Event\Record\RecordPreSave;
use Contenir\Extension\Api\Event\Settings\SettingsPreSave;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSection;
use Contenir\Extension\CampaignMonitor\Listener\KeepStoredKey;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Apis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class KeepStoredKeyTest extends TestCase
{
    private const string KEY = CampaignMonitorSection::API_KEY;

    private const string CLIENT = CampaignMonitorSection::CLIENT;

    private const string TYPED = 'typed-test-key';

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function emptyKeyProvider(): array
    {
        return [
            'an empty key'     => [[self::KEY => '', self::CLIENT => 'c2']],
            'a key of spaces'  => [[self::KEY => '  ', self::CLIENT => 'c2']],
            'no key posted'    => [[self::CLIENT => 'c2']],
            'a key not a text' => [[self::KEY => null, self::CLIENT => 'c2']],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nothingSavedProvider(): array
    {
        return [
            'nothing saved'    => [null],
            'an empty key'     => [''],
            'a key of spaces'  => ['  '],
            'a key not a text' => [42],
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array<string, mixed> The values the save goes on with
     */
    private static function save(array $before, array $after): array
    {
        $event = new SettingsPreSave(CampaignMonitorSection::NAME, new Change($before, $after));
        (new KeepStoredKey())->handle($event, Contexts::make());

        return $event->values();
    }

    /**
     * @param array<string, mixed> $after
     */
    #[Test]
    #[DataProvider('emptyKeyProvider')]
    public function keepsTheSavedKeyWhenTheFieldIsLeftEmpty(array $after): void
    {
        $values = self::save([self::KEY => Apis::KEY, self::CLIENT => 'c1'], $after);

        static::assertSame([Apis::KEY, 'c2'], [$values[self::KEY], $values[self::CLIENT]]);
    }

    #[Test]
    #[DataProvider('nothingSavedProvider')]
    public function leavesTheFieldEmptyWhenNoKeyIsSaved(mixed $saved): void
    {
        $values = self::save([self::KEY => $saved], [self::KEY => '']);

        static::assertSame('', $values[self::KEY]);
    }

    #[Test]
    public function passesOverOtherEvents(): void
    {
        $event = new RecordPreSave(
            'library.members.member',
            null,
            new Change([self::KEY => Apis::KEY], [self::KEY => '']),
        );
        (new KeepStoredKey())->handle($event, Contexts::make());

        static::assertSame('', $event->values()[self::KEY]);
    }

    #[Test]
    public function savesANewlyTypedKey(): void
    {
        $values = self::save([self::KEY => Apis::KEY], [self::KEY => self::TYPED]);

        static::assertSame(self::TYPED, $values[self::KEY]);
    }
}
