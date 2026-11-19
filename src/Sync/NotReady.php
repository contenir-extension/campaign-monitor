<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Sync;

use RuntimeException;

/**
 * The site isn't set up to sync yet: no pepper, bindings that can't be read, or no database.
 * The message says what to put right, and a missing pepper carries its own code, since the
 * setup check can add one.
 *
 * @api
 */
final class NotReady extends RuntimeException
{
    public const int NO_PEPPER = 1;

    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function noPepper(): self
    {
        return new self(
            'No pepper is set, so sign-ups can\'t be synced yet. The pepper is a secret that keeps the addresses of'
                . ' people who unsubscribed unreadable in the site\'s database.',
            self::NO_PEPPER,
        );
    }

    public function isNoPepper(): bool
    {
        return self::NO_PEPPER === $this->getCode();
    }
}
