<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Webhook;

use Contenir\Audience\Email;
use Contenir\Audience\Service\RemoteEvent;
use Contenir\Audience\Service\RemoteEventKind;
use Contenir\Extension\CampaignMonitor\Webhook\Event;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EventTest extends TestCase
{
    /**
     * @return array<string, array{string, RemoteEventKind}>
     */
    public static function deactivationProvider(): array
    {
        return [
            'unsubscribed' => ['Unsubscribed', RemoteEventKind::Unsubscribed],
            'bounced'      => ['Bounced', RemoteEventKind::Cleaned],
            'deleted'      => ['Deleted', RemoteEventKind::Forgotten],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function passedOverProvider(): array
    {
        $row = [
            'Type'         => 'Deactivate',
            'State'        => 'Unsubscribed',
            'Date'         => '2026-11-04 09:30:00',
            'EmailAddress' => 'ada@example.com',
        ];

        return [
            'a subscribe'                   => [['Type' => 'Subscribe'] + $row],
            'a state it doesn\'t know'      => [['State' => 'Active'] + $row],
            'no date'                       => [['Date' => ''] + $row],
            'a date with a zone'            => [['Date' => '2026-11-04T09:30:00+11:00'] + $row],
            'a bad address'                 => [['EmailAddress' => 'not an address'] + $row],
            'an update to other details'    => [['Type' => 'Update', 'OldEmailAddress' => 'ADA@example.com'] + $row],
            'an update with no old address' => [['Type' => 'Update'] + $row],
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[Test]
    #[DataProvider('passedOverProvider')]
    public function passesOverWhatTheSharedRulesNeedNotHear(array $row): void
    {
        static::assertNull(Event::read($row));
    }

    #[Test]
    public function readsAChangedAddressFromAnUpdate(): void
    {
        $event = Event::read([
            'Type'            => 'Update',
            'State'           => 'Active',
            'Date'            => '2026-11-04 09:30:00',
            'OldEmailAddress' => 'ada@example.com',
            'EmailAddress'    => 'ada@lovelace.example',
        ]);

        static::assertEquals(
            new RemoteEvent(
                RemoteEventKind::EmailChanged,
                Email::fromString('ada@example.com'),
                new DateTimeImmutable('2026-11-04 09:30:00 UTC'),
                Email::fromString('ada@lovelace.example'),
            ),
            $event,
        );
    }

    #[Test]
    #[DataProvider('deactivationProvider')]
    public function readsWhySomeoneLeftByTheStateTheyWereLeftIn(string $state, RemoteEventKind $kind): void
    {
        $event = Event::read([
            'Type'         => 'Deactivate',
            'State'        => $state,
            'Date'         => '2026-11-04 09:30:00',
            'EmailAddress' => 'Ada@Example.com',
            'Name'         => 'Ada',
        ]);

        static::assertEquals(
            new RemoteEvent(
                $kind,
                Email::fromString('ada@example.com'),
                new DateTimeImmutable('2026-11-04 09:30:00 UTC'),
            ),
            $event,
        );
    }
}
