<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Webhook;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Audience\Service\InvalidWebhook;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\WebhookInterface;
use Contenir\Extension\Api\Handler\WebhookResponse;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSettings;
use Contenir\Extension\CampaignMonitor\Connections;
use Contenir\Extension\CampaignMonitor\Sync\DatabaseSyncFactory;
use Contenir\Extension\CampaignMonitor\Sync\NotReady;
use Contenir\Extension\CampaignMonitor\Sync\SyncFactoryInterface;
use Contenir\Extension\CampaignMonitor\Sync\WebhookAddress;
use JsonException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function count;
use function hash_equals;
use function is_string;

/**
 * events, at /api/extension/campaign-monitor/events: what Campaign Monitor calls when someone on
 * a bound list unsubscribes, bounces, is marked as spam or changes address. Campaign Monitor
 * doesn't sign its calls, so the call must carry the add-on's secret, and its list must be the
 * binding's (CMN-2). Whoever left is suppressed, so the site never subscribes them again without
 * fresh consent.
 *
 * @api
 */
final readonly class EventsWebhook implements WebhookInterface
{
    public function __construct(
        private Connections $connections = new Connections(),
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    /**
     * @mago-expect analysis:mixed-assignment Query values are untyped; each is checked as text.
     */
    private static function query(ServerRequestInterface $request, string $name): string
    {
        $value = $request->getQueryParams()[$name] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @throws JsonException    Never: the answer is plain data.
     * @throws StorageException When suppression or sync state can't be written.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): WebhookResponse
    {
        $connection = CampaignMonitorSettings::from($context->settings())->connection();
        try {
            $sync    = $this->syncs->of($context);
            $binding = $sync->bindings->get(self::query($request, WebhookAddress::BINDING));
        } catch (NotReady) {
            return WebhookResponse::text('The site isn\'t set up to sync yet.', 503);
        } catch (UnknownBinding) {
            return WebhookResponse::text('No such binding.', 404);
        }

        if (null === $connection) {
            return WebhookResponse::text('The add-on isn\'t connected to Campaign Monitor.', 503);
        }

        try {
            $events = $this->connections->webhooks($connection)->parse($request, $binding);
        } catch (InvalidWebhook $e) {
            return WebhookResponse::text($e->getMessage(), 400);
        }

        $sync->remoteEvents()->apply($binding, $events);

        return WebhookResponse::json(['received' => count($events)]);
    }

    #[Override]
    public function verify(ServerRequestInterface $request, ExtensionContextInterface $context): bool
    {
        $secret = CampaignMonitorSettings::from($context->settings())->webhookSecret;
        $key    = self::query($request, WebhookAddress::KEY);

        return null !== $secret && '' !== $key && hash_equals($secret, $key);
    }
}
