<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor;

use Contenir\Extension\CampaignMonitor\Service\Api;
use Contenir\Extension\CampaignMonitor\Service\CampaignMonitorAdapter;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Contenir\Extension\CampaignMonitor\Webhook\CampaignMonitorWebhooks;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds what talks to Campaign Monitor over one HTTP client: Guzzle, with the API's timeouts,
 * unless a test gives it another.
 *
 * @api
 */
final readonly class Connections
{
    public function __construct(
        private ClientInterface $http = new Guzzle(Api::GUZZLE),
        private RequestFactoryInterface $requests = new HttpFactory(),
        private StreamFactoryInterface $streams = new HttpFactory(),
    ) {}

    public function adapter(Connection $connection): CampaignMonitorAdapter
    {
        return new CampaignMonitorAdapter($this->api($connection));
    }

    public function api(Connection $connection): Api
    {
        return new Api($connection, $this->http, $this->requests, $this->streams);
    }

    public function webhooks(Connection $connection): CampaignMonitorWebhooks
    {
        return new CampaignMonitorWebhooks($this->api($connection));
    }
}
