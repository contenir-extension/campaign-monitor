<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Webhook;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\InvalidWebhook;
use Contenir\Audience\Service\WebhookReceiverInterface;
use Contenir\Audience\Service\WebhookRegistration;
use Contenir\Extension\CampaignMonitor\Service\Api;
use Contenir\Extension\CampaignMonitor\Service\Json;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_map;
use function array_values;
use function is_string;
use function rawurlencode;

/**
 * Campaign Monitor's webhook for a list. Its calls aren't signed, so the add-on keeps a secret in
 * the webhook's address; this checks each call is for the binding's list (CMN-2), and reads the
 * deactivations and address changes in it.
 *
 * @api
 */
final readonly class CampaignMonitorWebhooks implements WebhookReceiverInterface
{
    /** The events registered: someone leaving the list, and someone changing their details */
    public const array EVENTS = ['Deactivate', 'Update'];

    public function __construct(
        private Api $api,
    ) {}

    #[Override]
    public function parse(ServerRequestInterface $request, Binding $binding): array
    {
        if ('POST' !== $request->getMethod()) {
            throw InvalidWebhook::because('Campaign Monitor sends its events by POST');
        }

        $payload = Json::document((string) $request->getBody());
        if (null === $payload) {
            throw InvalidWebhook::because('the body isn\'t JSON');
        }

        if (Json::string($payload, 'ListID') !== $binding->list) {
            throw InvalidWebhook::because('it isn\'t for the binding\'s list');
        }

        $rows = Json::rows($payload['Events'] ?? null);

        return array_values(array_filter(array_map(Event::read(...), $rows)));
    }

    #[Override]
    public function register(Binding $binding, string $url): WebhookRegistration
    {
        $reply = $this->api->send('POST', 'lists/' . rawurlencode($binding->list) . '/webhooks.json', [
            'Events'        => self::EVENTS,
            'Url'           => $url,
            'PayloadFormat' => 'json',
        ]);
        if (! $reply->ok()) {
            throw ConnectionRefused::because($reply->message());
        }

        if (! is_string($reply->body) || '' === $reply->body) {
            throw ConnectionRefused::because('Campaign Monitor didn\'t give the webhook an id');
        }

        return new WebhookRegistration($reply->body);
    }
}
