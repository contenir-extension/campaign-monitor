<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Field;

use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\CampaignMonitor\Field\ClientField;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ClientFieldTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'none chosen'          => [''],
            'nothing posted'       => [null],
            'a client ID'          => ['0123456789abcdef0123456789abcdef'],
            'in capitals, trimmed' => [' 0123456789ABCDEF0123456789ABCDEF '],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function refusedProvider(): array
    {
        return [
            'too short'          => ['0123456789abcdef'],
            'too long'           => ['0123456789abcdef0123456789abcdef0'],
            'not hexadecimal'    => ['0123456789abcdef0123456789abcdeg'],
            'a newline after it' => ["0123456789abcdef0123456789abcdef\n0"],
        ];
    }

    #[Test]
    #[DataProvider('acceptedProvider')]
    public function acceptsAClientIdOrNone(mixed $value): void
    {
        static::assertSame([], (new ClientField())->validate($value));
    }

    #[Test]
    public function isATextFieldTheScriptTurnsIntoAList(): void
    {
        $field = new ClientField();

        static::assertSame(
            [FieldType::Text, '[data-campaign-monitor-client]'],
            [$field->control(), $field->component()],
        );
    }

    #[Test]
    public function leavesTheListToTheScript(): void
    {
        static::assertSame([], (new ClientField())->options(Contexts::make(Settings::keyOnly())));
    }

    #[Test]
    #[DataProvider('refusedProvider')]
    public function refusesAnythingButAClientId(string $value): void
    {
        static::assertSame(
            ['Choose a client from the list, or enter its 32-character client ID.'],
            (new ClientField())->validate($value),
        );
    }

    #[Test]
    public function storesTheChoiceTrimmed(): void
    {
        static::assertSame(['c1', ''], [(new ClientField())->normalise(' c1 '), (new ClientField())->normalise(null)]);
    }
}
