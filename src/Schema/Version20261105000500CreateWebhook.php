<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Schema;

use Contenir\Audience\Schema\AbstractCreateWebhook;
use Contenir\Extension\CampaignMonitor\CampaignMonitorExtension;
use Override;

/**
 * The library's webhook table under the add-on's prefix.
 *
 * @internal Run by the host's migration runner.
 *
 * @mago-expect analysis:missing-constructor AbstractMigration sets its adapter and inspector in up() and preview().
 */
final class Version20261105000500CreateWebhook extends AbstractCreateWebhook
{
    #[Override]
    public function getVersion(): string
    {
        return '20261105000500';
    }

    #[Override]
    protected function prefix(): string
    {
        return CampaignMonitorExtension::PREFIX;
    }
}
