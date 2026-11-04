<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Service;

use SensitiveParameter;

use function base64_encode;

/**
 * What reaches one client's lists at Campaign Monitor: an API key, and the client whose lists
 * the add-on keeps. An agency's key reaches every client it manages, so the client is chosen too.
 *
 * @api
 */
final readonly class Connection
{
    public const string BASE = 'https://api.createsend.com/api/v3.3/';

    public function __construct(
        #[SensitiveParameter]
        private string $apiKey,
        public string $clientId,
    ) {}

    /**
     * The Authorization header: the key as the Basic user, with a placeholder password, as
     * Campaign Monitor asks.
     */
    public function authorization(): string
    {
        return 'Basic ' . base64_encode("{$this->apiKey}:x");
    }
}
