<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Operation;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSection;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSettings;
use Contenir\Extension\CampaignMonitor\Connections;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Contenir\Extension\CampaignMonitor\Sync\DatabaseSyncFactory;
use Contenir\Extension\CampaignMonitor\Sync\Secrets;
use Contenir\Extension\CampaignMonitor\Sync\Sync;
use Contenir\Extension\CampaignMonitor\Sync\SyncFactoryInterface;
use Contenir\Extension\CampaignMonitor\Sync\WebhookAddress;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Random\RandomException;

use function array_slice;
use function rawurlencode;
use function sprintf;

/**
 * Re-register webhooks: registers each binding's list with Campaign Monitor, at the add-on's
 * events webhook, after deleting the webhook it registered before. The webhook's secret is
 * made, and saved, the first time.
 *
 * @api
 */
final class RegisterWebhooks extends AbstractCampaignMonitorOperation
{
    public const string NAME = 'register-webhooks';

    public function __construct(
        Connections $connections = new Connections(),
        SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
        private readonly Secrets $secrets = new Secrets(),
    ) {
        parent::__construct($connections, $syncs);
    }

    /**
     * @throws ContainerExceptionInterface When the host can't give its site settings.
     * @throws RandomException             When no secret can be made.
     * @throws StorageException            When the registered webhooks can't be read or kept.
     * @throws UnknownSettingException     Never: the secret's field is declared.
     */
    #[Override]
    protected function run(
        Connection $connection,
        Sync $sync,
        CampaignMonitorSettings $settings,
        ExtensionContextInterface $context,
    ): OperationResultInterface {
        $bindings = $sync->bindings->all();
        if ([] === $bindings) {
            return OperationResult::message('warning', 'There are no bindings to register webhooks for.');
        }

        $secret = $settings->webhookSecret;
        if (null === $secret) {
            $secret = $this->secrets->make();
            $saved  = $context->settings()->set([CampaignMonitorSection::WEBHOOK_SECRET => $secret]);
            if (! $saved->succeeded()) {
                return OperationResult::message('danger', sprintf(
                    'The webhook\'s secret couldn\'t be saved: %s',
                    $saved->message,
                ));
            }
        }

        $messages = [];
        foreach ($bindings as $binding) {
            $address = WebhookAddress::for($context, $binding->name, $secret);
            if (null === $address) {
                return OperationResult::message(
                    'warning',
                    'Set the site\'s address in Site Settings first: Campaign Monitor calls the webhook there.',
                );
            }

            $messages[] = $this->register($connection, $sync, $binding, $address);
        }

        $result = OperationResult::message(...$messages[0]);
        foreach (array_slice($messages, offset: 1) as [$level, $text]) {
            $result = $result->withMessage($level, $text);
        }

        return $result;
    }

    /**
     * @return array{string, string} The message's level and text
     *
     * @throws StorageException
     */
    private function register(Connection $connection, Sync $sync, Binding $binding, string $address): array
    {
        $old = $sync->webhooks->find($binding->name);
        try {
            if (null !== $old) {
                $this->connections->api($connection)->send('DELETE', sprintf(
                    'lists/%s/webhooks/%s.json',
                    rawurlencode($binding->list),
                    rawurlencode($old->remoteId),
                ));
            }

            $registration = $this->connections->webhooks($connection)->register($binding, $address);
        } catch (ConnectionRefused $e) {
            return ['danger', sprintf('The webhook for %s wasn\'t registered. %s', $binding->name, $e->getMessage())];
        }

        $sync->webhooks->save(new RegisteredWebhook(
            $binding->name,
            $registration->id,
            $registration->signingSecret,
            $sync->clock->now(),
        ));

        return ['success', sprintf('Registered the webhook for %s.', $binding->name)];
    }
}
