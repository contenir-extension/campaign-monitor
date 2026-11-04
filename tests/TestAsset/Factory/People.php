<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Email;
use Contenir\Audience\Service\Person;

/**
 * Bindings and people as the shared rules hand them to the adapter.
 */
final class People
{
    public const string LIST = 'a1b2c3d4e5f6';

    /**
     * @param array<string, string|int|float|bool> $options
     */
    public static function binding(array $options = []): Binding
    {
        return new Binding('newsletter', self::LIST, options: $options);
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     */
    public static function person(
        string $email = 'ada@example.com',
        array $values = [],
        bool $resubscribe = false,
    ): Person {
        return new Person(Email::fromString($email), $values, resubscribe: $resubscribe);
    }
}
