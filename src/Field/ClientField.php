<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Field;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Field\FieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSettings;
use Contenir\Extension\CampaignMonitor\Connections;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Override;

use function is_string;
use function trim;

/**
 * The client whose lists the add-on keeps, chosen from those the saved API key reaches: an
 * agency's key reaches every client it manages. Until a key is saved, or while Campaign Monitor
 * refuses it, there is nothing to choose.
 *
 * @api
 */
final readonly class ClientField implements FieldTypeInterface
{
    public const string NAME = 'client';

    public function __construct(
        private Connections $connections = new Connections(),
    ) {}

    #[Override]
    public function component(): ?string
    {
        return null;
    }

    #[Override]
    public function control(): FieldType
    {
        return FieldType::Select;
    }

    #[Override]
    public function normalise(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : '';
    }

    #[Override]
    public function options(ExtensionContextInterface $context): array
    {
        $key = CampaignMonitorSettings::from($context->settings())->apiKey;
        if (null === $key) {
            return [];
        }

        try {
            $clients = $this->connections->adapter(new Connection($key, ''))->clients();
        } catch (ConnectionRefused) {
            return [];
        }

        $options = [];
        foreach ($clients as $client) {
            $options[$client->id] = $client->name;
        }

        return $options;
    }

    #[Override]
    public function validate(mixed $value): array
    {
        return [];
    }
}
