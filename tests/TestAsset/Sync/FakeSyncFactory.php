<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\CampaignMonitor\Sync\NotReady;
use Contenir\Extension\CampaignMonitor\Sync\Sync;
use Contenir\Extension\CampaignMonitor\Sync\SyncFactoryInterface;
use Override;

/**
 * Gives the same sync whatever the context, or says the site isn't ready when it has none.
 */
final readonly class FakeSyncFactory implements SyncFactoryInterface
{
    public function __construct(
        private ?Sync $sync,
        private string $reason = 'The add-on\'s config file has no pepper to hash email addresses with.',
    ) {}

    public static function notReady(string $reason = 'The add-on\'s config file has no pepper to hash email addresses with.'): self
    {
        return new self(null, $reason);
    }

    #[Override]
    public function of(ExtensionContextInterface $context): Sync
    {
        return $this->sync ?? throw NotReady::because($this->reason);
    }
}
