<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Service;

use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\OutcomeKind;
use Contenir\Extension\CampaignMonitor\Service\Outcomes;
use Contenir\Extension\CampaignMonitor\Service\Reply;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OutcomesTest extends TestCase
{
    /**
     * @return array<string, array{int|null, OutcomeKind}>
     */
    public static function codeProvider(): array
    {
        return [
            'invalid address' => [1, OutcomeKind::Failed],
            'not in the list' => [203, OutcomeKind::Failed],
            'suppressed'      => [204, OutcomeKind::Forgotten],
            'deleted'         => [205, OutcomeKind::Forgotten],
            'unsubscribed'    => [206, OutcomeKind::Failed],
            'bounced'         => [207, OutcomeKind::Failed],
            'no code at all'  => [null, OutcomeKind::Failed],
        ];
    }

    #[Test]
    public function countsSomeoneNotOnTheListAsAlreadyOffIt(): void
    {
        static::assertEquals(
            Outcome::done(),
            Outcomes::gone(new Reply(400, ['Code' => 203, 'Message' => 'Subscriber not in list'])),
        );
    }

    #[Test]
    public function failsWhatCampaignMonitorRefused(): void
    {
        static::assertEquals(
            Outcome::failed('Invalid Email Address'),
            Outcomes::of(new Reply(400, ['Code' => 1, 'Message' => 'Invalid Email Address'])),
        );
    }

    #[Test]
    public function forgetsAnAddressCampaignMonitorDeleted(): void
    {
        static::assertSame(
            OutcomeKind::Forgotten,
            Outcomes::of(new Reply(400, ['Code' => 205, 'Message' => 'Is Deleted']))->kind,
        );
    }

    #[Test]
    public function isDoneWhenCampaignMonitorSucceeded(): void
    {
        static::assertEquals(Outcome::done(), Outcomes::of(new Reply(200)));
    }

    #[Test]
    public function keepsCampaignMonitorsReasonForAnError(): void
    {
        static::assertSame('In Suppression List', Outcomes::code(204, 'In Suppression List')->reason);
    }

    #[Test]
    #[DataProvider('codeProvider')]
    public function neverRetriesAnAddressCampaignMonitorWontTakeBack(?int $code, OutcomeKind $kind): void
    {
        static::assertSame($kind, Outcomes::code($code, 'Why')->kind);
    }

    #[Test]
    public function takesSomeoneOffTheListAsAnyOtherReplyOtherwise(): void
    {
        static::assertEquals(
            Outcome::failed('Invalid Email Address'),
            Outcomes::gone(new Reply(400, ['Code' => 1, 'Message' => 'Invalid Email Address'])),
        );
    }

    #[Test]
    public function triesAgainWhenCampaignMonitorIsBusyAfterTheWaitItAsks(): void
    {
        static::assertEquals(
            Outcome::retry('Rate limit exceeded', 30),
            Outcomes::of(new Reply(429, ['Code' => 429, 'Message' => 'Rate limit exceeded'], 30)),
        );
    }
}
