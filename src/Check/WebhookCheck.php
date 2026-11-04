<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Check;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSettings;
use Contenir\Extension\CampaignMonitor\Connections;
use Contenir\Extension\CampaignMonitor\Service\Api;
use Contenir\Extension\CampaignMonitor\Service\Json;
use Contenir\Extension\CampaignMonitor\Sync\DatabaseSyncFactory;
use Contenir\Extension\CampaignMonitor\Sync\NotReady;
use Contenir\Extension\CampaignMonitor\Sync\SyncFactoryInterface;
use Override;

use function implode;
use function rawurlencode;
use function sprintf;

/**
 * Whether each binding's webhook is registered and Campaign Monitor still calls it: a webhook it
 * deleted or switched off means unsubscribes no longer reach the site.
 *
 * @api
 */
final readonly class WebhookCheck implements CheckInterface
{
    public const string NAME = 'webhooks';

    public function __construct(
        private Connections $connections = new Connections(),
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    /**
     * What's wrong with one binding's webhook, or null when Campaign Monitor calls it.
     *
     * @throws ConnectionRefused
     */
    private static function problem(Api $api, Binding $binding, ?RegisteredWebhook $webhook): ?string
    {
        if (null === $webhook) {
            return sprintf('the webhook for %s isn\'t registered', $binding->name);
        }

        $reply = $api->send('GET', sprintf('lists/%s/webhooks.json', rawurlencode($binding->list)));
        if (! $reply->ok()) {
            return sprintf('the webhooks for %s couldn\'t be read: %s', $binding->name, $reply->message());
        }

        foreach (Json::rows($reply->body) as $row) {
            if (Json::string($row, 'WebhookID') === $webhook->remoteId) {
                return 'Active' === Json::string($row, 'Status')
                    ? null
                    : sprintf('the webhook for %s is switched off at Campaign Monitor', $binding->name);
            }
        }

        return sprintf('the webhook for %s is no longer at Campaign Monitor', $binding->name);
    }

    /**
     * @throws StorageException When the registered webhooks can't be read.
     */
    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        $connection = CampaignMonitorSettings::from($context->settings())->connection();
        try {
            $sync = $this->syncs->of($context);
        } catch (NotReady) {
            return CheckResult::skipped('The site isn\'t set up to sync yet.');
        }

        if (null === $connection) {
            return CheckResult::skipped('The add-on isn\'t connected to Campaign Monitor yet.');
        }

        $api      = $this->connections->api($connection);
        $webhooks = $sync->webhooks->all();
        $problems = [];
        try {
            foreach ($sync->bindings->all() as $binding) {
                $problem = self::problem($api, $binding, $webhooks[$binding->name] ?? null);
                if (null !== $problem) {
                    $problems[] = $problem;
                }
            }
        } catch (ConnectionRefused) {
            return CheckResult::skipped('Campaign Monitor refused the connection: see the connection check.');
        }

        return [] === $problems
            ? CheckResult::success('Campaign Monitor calls the webhook for every binding.')
            : CheckResult::warning(sprintf(
                'Unsubscribes and bounces don\'t reach the site: %s. Press Re-register webhooks in'
                    . ' the Campaign Monitor settings.',
                implode('; ', $problems),
            ));
    }

    #[Override]
    public function label(): string
    {
        return 'Campaign Monitor webhooks';
    }
}
