<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Service;

use Contenir\Extension\CampaignMonitor\Service\Json;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class JsonTest extends TestCase
{
    #[Test]
    public function readsAJsonObject(): void
    {
        static::assertSame(['ListID' => 'abc'], Json::document('{"ListID":"abc"}'));
    }

    #[Test]
    public function readsAnEmptyObjectFromAnotherType(): void
    {
        static::assertSame(
            [[], [], []],
            [
                Json::object(['BasicDetails' => 'c1'], 'BasicDetails'),
                Json::object([], 'BasicDetails'),
                Json::object(3, 'BasicDetails'),
            ],
        );
    }

    #[Test]
    public function readsAnEmptyStringFromAnotherType(): void
    {
        static::assertSame(
            ['', '', ''],
            [Json::string(['ListID' => 1], 'ListID'), Json::string([], 'ListID'), Json::string(null, 'ListID')],
        );
    }

    #[Test]
    public function readsAnInteger(): void
    {
        static::assertSame(204, Json::int(['Code' => 204], 'Code'));
    }

    #[Test]
    public function readsAnObject(): void
    {
        static::assertSame(
            ['ClientID' => 'c1'],
            Json::object(['BasicDetails' => ['ClientID' => 'c1']], 'BasicDetails'),
        );
    }

    #[Test]
    public function readsAString(): void
    {
        static::assertSame('abc', Json::string(['ListID' => 'abc'], 'ListID'));
    }

    #[Test]
    public function readsNoIntegerFromAnotherType(): void
    {
        static::assertSame(
            [null, null, null],
            [Json::int(['Code' => '204'], 'Code'), Json::int([], 'Code'), Json::int('Code', 'Code')],
        );
    }

    #[Test]
    public function readsNoRowsFromSomethingOtherThanAnArray(): void
    {
        static::assertSame([], Json::rows('rows'));
    }

    #[Test]
    public function readsNothingFromJsonThatIsNotAnObjectOrArray(): void
    {
        static::assertNull(Json::document('"abc"'));
    }

    #[Test]
    public function readsNothingFromTextThatIsNotJson(): void
    {
        static::assertNull(Json::document('ListID=abc'));
    }

    #[Test]
    public function readsOnlyTheObjectsInAnArray(): void
    {
        static::assertSame([['a' => 1], ['b' => 2]], Json::rows([['a' => 1], 'x', 'k' => ['b' => 2]]));
    }
}
