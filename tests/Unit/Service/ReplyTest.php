<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Service;

use Contenir\Extension\CampaignMonitor\Service\Reply;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReplyTest extends TestCase
{
    /**
     * @return array<string, array{int, bool}>
     */
    public static function retryProvider(): array
    {
        return [
            'unreached'    => [0, true],
            'bad request'  => [400, false],
            'busy'         => [429, true],
            'just below'   => [499, false],
            'server error' => [500, true],
            'unavailable'  => [503, true],
        ];
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function successProvider(): array
    {
        return [
            'just below 200' => [199, false],
            '200'            => [200, true],
            '201'            => [201, true],
            '299'            => [299, true],
            '300'            => [300, false],
        ];
    }

    #[Test]
    public function givesCampaignMonitorsMessage(): void
    {
        static::assertSame(
            'Invalid Email Address',
            (new Reply(400, ['Code' => 1, 'Message' => 'Invalid Email Address']))->message(),
        );
    }

    #[Test]
    public function givesTheStatusWhenThereIsNoMessage(): void
    {
        static::assertSame('Campaign Monitor answered 502', (new Reply(502, '<html>'))->message());
    }

    #[Test]
    public function givesWhyARequestNeverArrived(): void
    {
        static::assertSame('Timed out', (new Reply(0, failure: 'Timed out'))->message());
    }

    #[Test]
    public function hasNoCodeWithoutAnErrorBody(): void
    {
        static::assertNull((new Reply(201, 'webhook-id'))->code());
    }

    #[Test]
    #[DataProvider('retryProvider')]
    public function isWorthRetryingOnlyWhenBusyFailingOrUnreached(int $status, bool $retriable): void
    {
        static::assertSame($retriable, (new Reply($status))->retriable());
    }

    #[Test]
    public function readsCampaignMonitorsCodeFromAnError(): void
    {
        static::assertSame(204, (new Reply(400, ['Code' => 204, 'Message' => 'In Suppression List']))->code());
    }

    #[Test]
    #[DataProvider('successProvider')]
    public function succeedsOnlyWithATwoHundredStatus(int $status, bool $ok): void
    {
        static::assertSame($ok, (new Reply($status))->ok());
    }
}
