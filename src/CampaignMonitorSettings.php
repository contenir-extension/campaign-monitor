<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor;

use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use SensitiveParameter;

use function is_string;
use function trim;

/**
 * The Campaign Monitor section's saved values, read once: the API key, the client whose lists
 * the add-on keeps, and the secret in the webhook's address. Blank reads as not set.
 *
 * @api
 */
final readonly class CampaignMonitorSettings
{
    public function __construct(
        #[SensitiveParameter]
        public ?string $apiKey,
        public ?string $clientId,
        #[SensitiveParameter]
        public ?string $webhookSecret,
    ) {}

    /**
     * @throws UnknownSettingException Never: every field read is declared.
     */
    public static function from(SettingsInterface $settings): self
    {
        return new self(
            self::text($settings->get(CampaignMonitorSection::API_KEY)),
            self::text($settings->get(CampaignMonitorSection::CLIENT)),
            self::text($settings->get(CampaignMonitorSection::WEBHOOK_SECRET)),
        );
    }

    private static function text(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return '' === $text ? null : $text;
    }

    /**
     * The connection to the chosen client's lists, or null until the key and client are saved.
     */
    public function connection(): ?Connection
    {
        return null === $this->apiKey || null === $this->clientId
            ? null
            : new Connection(
                $this->apiKey,
                $this->clientId,
            );
    }

    /**
     * What still has to be entered before the add-on can reach the client's lists, or null when
     * nothing does.
     */
    public function missing(): ?string
    {
        return match (true) {
            null === $this->apiKey => 'Enter the API key, and save, first.',
            null === $this->clientId => 'Choose the client, and save, first.',
            default => null,
        };
    }
}
