<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Field;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Field\FieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Override;

use function is_string;
use function preg_match;
use function trim;

/**
 * The client whose lists the add-on keeps, chosen from those the API key reaches: an agency's key reaches every
 * client it manages. It is a text field holding the client's ID, which the add-on's script turns into a list of the
 * clients, filled from {@see \Contenir\Extension\CampaignMonitor\Handler\ClientsLookup} as the key is typed. A select
 * drawn by the CMS would accept only the clients of the key already saved, so a key and its client could never be
 * saved together.
 *
 * @api
 */
final readonly class ClientField implements FieldTypeInterface
{
    public const string NAME = 'client';

    /**
     * The attribute the CMS gives the field for the add-on's script to find it.
     */
    public const string COMPONENT = '[data-campaign-monitor-client]';

    #[Override]
    public function component(): string
    {
        return self::COMPONENT;
    }

    #[Override]
    public function control(): FieldType
    {
        return FieldType::Text;
    }

    #[Override]
    public function normalise(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : '';
    }

    #[Override]
    public function options(ExtensionContextInterface $context): array
    {
        return [];
    }

    /**
     * A client's ID is 32 hexadecimal characters; empty means none chosen yet.
     */
    #[Override]
    public function validate(mixed $value): array
    {
        $id = is_string($value) ? trim($value) : '';

        return (
            '' === $id || 1 === preg_match('/^[0-9a-f]{32}$/i', $id)
                ? []
                : ['Choose a client from the list, or enter its 32-character client ID.']
        );
    }
}
