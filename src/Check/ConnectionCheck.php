<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Check;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSettings;
use Contenir\Extension\CampaignMonitor\Connections;
use Override;

use function sprintf;

/**
 * Whether Campaign Monitor accepts the saved key for the chosen client. While it refuses it,
 * nothing is sent and everything waits, so the check fails with a way to the settings.
 *
 * @api
 */
final readonly class ConnectionCheck implements CheckInterface
{
    public const string NAME = 'connection';

    public function __construct(
        private Connections $connections = new Connections(),
    ) {}

    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        $settings   = CampaignMonitorSettings::from($context->settings());
        $connection = $settings->connection();
        if (null === $connection) {
            return CheckResult::warning((string) $settings->missing());
        }

        try {
            $account = $this->connections->adapter($connection)->verifyConnection();
        } catch (ConnectionRefused $e) {
            return CheckResult::failure(
                sprintf('%s Nothing is sent until it\'s put right; everything waits.', $e->getMessage()),
                new GuidedFix(
                    'Open the connection settings',
                    'Check the API key and client in Site Settings > Integration > Campaign Monitor, save, and'
                        . ' press Test connection. Sending starts again in the next minute.',
                ),
            );
        }

        return CheckResult::success(sprintf('Connected to %s.', $account->name));
    }

    #[Override]
    public function label(): string
    {
        return 'Campaign Monitor connection';
    }
}
