<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Webhook;

use Contenir\Audience\Email;
use Contenir\Audience\Exception\InvalidEmail;
use Contenir\Audience\Service\RemoteEvent;
use Contenir\Audience\Service\RemoteEventKind;
use Contenir\Extension\CampaignMonitor\Service\Json;
use DateTimeImmutable;

/**
 * One event in a webhook call, as the shared rules take it. A deactivation says why by the
 * state the person was left in; an update matters only when it changed their address. Anything
 * else, or anything incomplete, is passed over. Its dates carry no zone, so they're read as UTC.
 *
 * @api
 */
final class Event
{
    /** How a deactivated person was left, by their state */
    public const array DEACTIVATED = [
        'Unsubscribed' => RemoteEventKind::Unsubscribed,
        'Bounced'      => RemoteEventKind::Cleaned,
        'Deleted'      => RemoteEventKind::Forgotten,
    ];

    /**
     * @param array<array-key, mixed> $row
     */
    public static function read(array $row): ?RemoteEvent
    {
        $at    = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s e', Json::string($row, 'Date') . ' UTC');
        $email = self::email(Json::string($row, 'EmailAddress'));
        if (false === $at || null === $email) {
            return null;
        }

        return match (Json::string($row, 'Type')) {
            'Deactivate' => self::deactivated($row, $email, $at),
            'Update'     => self::moved($row, $email, $at),
            default      => null,
        };
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function deactivated(array $row, Email $email, DateTimeImmutable $at): ?RemoteEvent
    {
        $kind = self::DEACTIVATED[Json::string($row, 'State')] ?? null;

        return null === $kind ? null : new RemoteEvent($kind, $email, $at);
    }

    private static function email(string $address): ?Email
    {
        try {
            return Email::fromString($address);
        } catch (InvalidEmail) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function moved(array $row, Email $email, DateTimeImmutable $at): ?RemoteEvent
    {
        $old = self::email(Json::string($row, 'OldEmailAddress'));

        return null !== $old && $old->value !== $email->value
            ? new RemoteEvent(RemoteEventKind::EmailChanged, $old, $at, $email)
            : null;
    }
}
