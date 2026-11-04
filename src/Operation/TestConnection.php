<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Operation;

use Contenir\Audience\Service\Account;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\RemoteList;
use Contenir\Extension\Api\Operation\OperationContextInterface;
use Contenir\Extension\Api\Operation\OperationHandlerInterface;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSettings;
use Contenir\Extension\CampaignMonitor\Connections;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Override;

use function array_map;
use function implode;
use function sprintf;

/**
 * Test connection: checks the saved key, without sending anyone. With no client chosen yet it
 * lists the clients the key reaches; with one, it names the client and its lists.
 *
 * @api
 */
final readonly class TestConnection implements OperationHandlerInterface
{
    public const string NAME = 'test-connection';

    public function __construct(
        private Connections $connections = new Connections(),
    ) {}

    #[Override]
    public function handle(OperationContextInterface $context): OperationResultInterface
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
        try {
            return match (true) {
                null !== $connection => $this->client($connection),
                null !== $settings->apiKey => $this->clients($settings->apiKey),
                default => OperationResult::message('warning', (string) $settings->missing()),
            };
        } catch (ConnectionRefused $e) {
            return OperationResult::message('danger', $e->getMessage());
        }
    }

    /**
     * @throws ConnectionRefused
     */
    private function client(Connection $connection): OperationResult
    {
        $adapter = $this->connections->adapter($connection);
        $account = $adapter->verifyConnection();
        $lists   = array_map(static fn(RemoteList $list): string => $list->name, $adapter->lists());

        return OperationResult::message('success', sprintf(
            'Connected to %s. %s',
            $account->name,
            [] === $lists ? 'It has no lists yet.' : sprintf('Its lists: %s.', implode(', ', $lists)),
        ));
    }

    /**
     * @throws ConnectionRefused
     */
    private function clients(string $key): OperationResult
    {
        $clients = $this->connections->adapter(new Connection($key, ''))->clients();

        return [] === $clients
            ? OperationResult::message('warning', 'The key works, but reaches no clients.')
            : OperationResult::message('warning', sprintf(
                'The key works. Choose the client whose lists the site keeps, and save: %s.',
                implode(', ', array_map(static fn(Account $client): string => $client->name, $clients)),
            ));
    }
}
