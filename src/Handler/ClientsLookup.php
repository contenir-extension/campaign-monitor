<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Handler;

use Contenir\Audience\Service\Account;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSettings;
use Contenir\Extension\CampaignMonitor\Connections;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

/**
 * The clients an API key reaches, as JSON, for the settings form to fill its Client list while the editor types the
 * key. The key comes in a header, never the address, so it stays out of logs; with none, the saved key is asked.
 * Campaign Monitor refusing the key answers 422 with its reason, for the form to show beside the field.
 *
 * @api
 */
final readonly class ClientsLookup implements HandlerInterface
{
    public const string NAME = 'clients';

    public const string KEY_HEADER = 'X-Campaign-Monitor-Key';

    public function __construct(
        private Connections $connections = new Connections(),
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        if ('GET' !== $request->getMethod()) {
            return HandlerResult::json(['ok' => false, 'error' => 'Ask for the clients with GET.'], 405);
        }

        $typed = $request->getHeaderLine(self::KEY_HEADER);
        $key   = '' === $typed ? CampaignMonitorSettings::from($context->settings())->apiKey : $typed;
        if (null === $key) {
            return HandlerResult::json(['ok' => false, 'error' => 'Enter the API key.'], 400);
        }

        try {
            $clients = $this->connections->adapter(new Connection($key, ''))->clients();
        } catch (ConnectionRefused $e) {
            return HandlerResult::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return HandlerResult::json([
            'ok'      => true,
            'clients' => array_map(
                /** @return array{id: string, name: string} */
                static fn(Account $client): array => ['id' => $client->id, 'name' => $client->name],
                $clients,
            ),
        ]);
    }
}
