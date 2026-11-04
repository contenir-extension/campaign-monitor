<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Service;

use Contenir\Extension\CampaignMonitor\Service\Subscriber;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\People;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SubscriberTest extends TestCase
{
    /**
     * @return array<string, array{string|int|float|bool|null, string}>
     */
    public static function consentProvider(): array
    {
        return [
            'true'          => [true, 'Yes'],
            'false'         => [false, 'No'],
            'yes, any case' => [' YES ', 'Yes'],
            'the word true' => ['true', 'Yes'],
            'one'           => [1, 'Yes'],
            'the text one'  => ['1', 'Yes'],
            'no'            => ['no', 'No'],
            'zero'          => [0, 'No'],
            'empty'         => ['', 'No'],
            'nothing'       => [null, 'No'],
            'anything else' => ['maybe', 'No'],
        ];
    }

    #[Test]
    public function clearsAFieldWithNoValue(): void
    {
        $body = Subscriber::body(People::binding(), People::person(values: ['website' => null]));

        static::assertSame([['Key' => 'website', 'Value' => '', 'Clear' => true]], $body['CustomFields']);
    }

    #[Test]
    public function sendsAnEmptyNameForANameWithNoValue(): void
    {
        $body = Subscriber::body(People::binding(), People::person(values: ['Name' => null]));

        static::assertSame('', $body['Name'] ?? null);
    }

    #[Test]
    public function sendsTheEmailAndCustomFields(): void
    {
        $body = Subscriber::body(People::binding(), People::person(values: [
            'website' => 'https://example.com',
            'age'     => 36,
        ]));

        static::assertSame(
            [
                'EmailAddress'   => 'ada@example.com',
                'CustomFields'   => [
                    ['Key' => 'website', 'Value' => 'https://example.com'],
                    ['Key' => 'age', 'Value' => '36'],
                ],
                'ConsentToTrack' => 'No',
            ],
            $body,
        );
    }

    #[Test]
    public function sendsTheNameAsCampaignMonitorsOwnField(): void
    {
        $body = Subscriber::body(People::binding(), People::person(values: ['Name' => 'Ada Lovelace']));

        static::assertSame(['Ada Lovelace', []], [$body['Name'] ?? null, $body['CustomFields']]);
    }

    #[Test]
    public function sendsYesOrNoForATrueOrFalseField(): void
    {
        $body = Subscriber::body(People::binding(), People::person(values: ['member' => true, 'staff' => false]));

        static::assertSame(
            [['Key' => 'member', 'Value' => 'Yes'], ['Key' => 'staff', 'Value' => 'No']],
            $body['CustomFields'],
        );
    }

    #[Test]
    #[DataProvider('consentProvider')]
    public function tracksOnlyWhenAMappedFieldSaysYes(string|int|float|bool|null $value, string $consent): void
    {
        $body = Subscriber::body(
            People::binding([Subscriber::CONSENT_OPTION => 'yes']),
            People::person(values: [Subscriber::CONSENT_TO_TRACK => $value]),
        );

        static::assertSame([$consent, []], [$body['ConsentToTrack'], $body['CustomFields']]);
    }

    #[Test]
    public function tracksWhenTheBindingSaysYesAndNoFieldIsMapped(): void
    {
        $body = Subscriber::body(People::binding([Subscriber::CONSENT_OPTION => 'Yes']), People::person());

        static::assertSame('Yes', $body['ConsentToTrack']);
    }
}
