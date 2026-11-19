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
        private ?string $reason = null,
    ) {}

    /**
     * Not ready for $reason, or for want of a pepper when none is given.
     */
    public static function notReady(?string $reason = null): self
    {
        return new self(null, $reason);
    }

    #[Override]
    public function of(ExtensionContextInterface $context): Sync
    {
        return $this->sync ?? throw null === $this->reason ? NotReady::noPepper() : NotReady::because($this->reason);
    }
}
