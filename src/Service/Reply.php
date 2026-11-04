<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Service;

/**
 * What Campaign Monitor answered to one request: its status, its decoded JSON body, and how long
 * it asked to be left alone, if it did. A request that never reached it has status 0.
 *
 * @api
 */
final readonly class Reply
{
    public function __construct(
        public int $status,
        public mixed $body = null,
        public ?int $retryAfter = null,
        private string $failure = '',
    ) {}

    /**
     * Campaign Monitor's own error code, from an error's body.
     */
    public function code(): ?int
    {
        return Json::int($this->body, 'Code');
    }

    /**
     * Why the request didn't succeed, as Campaign Monitor put it where it did.
     */
    public function message(): string
    {
        $message = Json::string($this->body, 'Message');
        if ('' !== $message) {
            return $message;
        }

        return '' === $this->failure ? "Campaign Monitor answered {$this->status}" : $this->failure;
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Whether trying again later may succeed: it was busy, failed, or couldn't be reached.
     */
    public function retriable(): bool
    {
        return 0 === $this->status || 429 === $this->status || $this->status >= 500;
    }
}
