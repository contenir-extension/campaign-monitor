<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Service;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Service\Person;

use function array_diff_key;
use function array_key_exists;
use function array_keys;
use function array_map;
use function in_array;
use function is_bool;
use function strtolower;
use function trim;

/**
 * A person as Campaign Monitor takes them: their email, name and custom fields, and whether they
 * agreed to be tracked. Tracking is No unless a mapped field or the binding says Yes, and never
 * Unchanged (CMN-1).
 *
 * @api
 */
final class Subscriber
{
    /** The binding option that sets consent to track for everyone it sends: "yes" or "no" */
    public const string CONSENT_OPTION = 'consent_to_track';

    /** The field a binding maps to say whether someone agreed to be tracked */
    public const string CONSENT_TO_TRACK = 'ConsentToTrack';

    /** Campaign Monitor's own field for a subscriber's name */
    public const string NAME = 'Name';

    /** The fields Campaign Monitor holds outside a list's custom fields */
    private const array OWN_FIELDS = [self::NAME => null, self::CONSENT_TO_TRACK => null];

    /** Values read as Yes, in any case */
    private const array YES = ['yes', 'true', '1'];

    /**
     * @return array<string, mixed>
     */
    public static function body(Binding $binding, Person $person): array
    {
        $values = $person->values;
        $custom = array_diff_key($values, self::OWN_FIELDS);
        $body   = [
            'EmailAddress'   => $person->email->value,
            'CustomFields'   => array_map(self::field(...), array_keys($custom), $custom),
            'ConsentToTrack' => self::yes(
                array_key_exists(self::CONSENT_TO_TRACK, $values)
                    ? $values[self::CONSENT_TO_TRACK]
                    : $binding->option(self::CONSENT_OPTION),
            )
                ? 'Yes'
                : 'No',
        ];

        return array_key_exists(self::NAME, $values) ? [...$body, self::NAME => (string) $values[self::NAME]] : $body;
    }

    /**
     * One custom field: an empty value clears it.
     *
     * @return array{Key: string, Value: string, Clear?: true}
     */
    private static function field(int|string $key, string|int|float|bool|null $value): array
    {
        if (null === $value) {
            return ['Key' => (string) $key, 'Value' => '', 'Clear' => true];
        }

        return [
            'Key'   => (string) $key,
            'Value' => match (true) {
                true === $value => 'Yes',
                false === $value => 'No',
                default => (string) $value,
            },
        ];
    }

    private static function yes(string|int|float|bool|null $value): bool
    {
        return is_bool($value) ? $value : in_array(strtolower(trim((string) $value)), self::YES, strict: true);
    }
}
