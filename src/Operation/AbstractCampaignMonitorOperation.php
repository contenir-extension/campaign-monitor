<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Operation;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Operation\OperationContextInterface;
use Contenir\Extension\Api\Operation\OperationHandlerInterface;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSettings;
use Contenir\Extension\CampaignMonitor\Connections;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Contenir\Extension\CampaignMonitor\Sync\DatabaseSyncFactory;
use Contenir\Extension\CampaignMonitor\Sync\NotReady;
use Contenir\Extension\CampaignMonitor\Sync\Sync;
use Contenir\Extension\CampaignMonitor\Sync\SyncFactoryInterface;
use Override;

/**
 * An operation on the Campaign Monitor section that syncs: it runs once the key and client are
 * saved and the site is set up to sync, and otherwise says what to put right.
 *
 * @api
 */
abstract class AbstractCampaignMonitorOperation implements OperationHandlerInterface
{
    public function __construct(
        protected readonly Connections $connections = new Connections(),
        protected readonly SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    #[Override]
    final public function handle(OperationContextInterface $context): OperationResultInterface
    {
        $extension = $context->extension();
        if (null === $extension) {
            return OperationResult::message(
                'danger',
                'The CMS ran this without the Campaign Monitor add-on\'s settings.',
            );
        }

        $settings   = CampaignMonitorSettings::from($extension->settings());
        $connection = $settings->connection();
        if (null === $connection) {
            return OperationResult::message('warning', (string) $settings->missing());
        }

        try {
            $sync = $this->syncs->of($extension);
        } catch (NotReady $e) {
            return OperationResult::message('warning', $e->getMessage());
        }

        return $this->run($connection, $sync, $settings, $extension);
    }

    /**
     * Does the operation's work over the saved connection.
     */
    abstract protected function run(
        Connection $connection,
        Sync $sync,
        CampaignMonitorSettings $settings,
        ExtensionContextInterface $context,
    ): OperationResultInterface;
}
