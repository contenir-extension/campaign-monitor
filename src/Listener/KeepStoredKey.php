<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Listener;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Event\EventInterface;
use Contenir\Extension\Api\Event\Settings\SettingsPreSave;
use Contenir\Extension\Api\Listener\ListenerInterface;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSection;
use Override;

use function is_string;
use function trim;

/**
 * keep-api-key, on SettingsPreSave: the settings form never shows the saved API key back, so a save with the key
 * field left empty keeps the saved key rather than clearing it. The client can then be chosen or changed without
 * typing the key again. A host that already keeps an empty secret's value leaves nothing for it to do.
 *
 * @api
 */
final readonly class KeepStoredKey implements ListenerInterface
{
    public const string NAME = 'keep-api-key';

    /**
     * The API key among $values, or empty when it isn't text.
     *
     * @param array<string, mixed> $values
     */
    private static function text(array $values): string
    {
        /** @var mixed $key */
        $key = $values[CampaignMonitorSection::API_KEY] ?? null;

        return is_string($key) ? $key : '';
    }

    #[Override]
    public function handle(EventInterface $event, ExtensionContextInterface $context): void
    {
        if (! $event instanceof SettingsPreSave) {
            return;
        }

        $typed = self::text($event->values());
        $saved = self::text($event->change()->before);
        if ('' === trim($saved) || '' !== trim($typed)) {
            return;
        }

        $event->setValue(CampaignMonitorSection::API_KEY, $saved);
    }
}
