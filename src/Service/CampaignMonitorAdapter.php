<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Service;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Service\Account;
use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\Person;
use Contenir\Audience\Service\RemoteField;
use Contenir\Audience\Service\RemoteList;
use Contenir\Audience\Service\ServiceAdapterInterface;
use Override;

use function array_filter;
use function array_map;
use function array_values;
use function ksort;
use function rawurlencode;
use function trim;

/**
 * Campaign Monitor's half of keeping a list in step. People join through imports of up to a
 * thousand, without autoresponders, and are resubscribed only when they agreed afresh; the
 * list's own setting decides whether they confirm by email first. Every other change is one
 * request per person.
 *
 * @api
 *
 * @mago-expect lint:too-many-methods One method per method of the service interface, plus clients() and the requests they share.
 */
final readonly class CampaignMonitorAdapter implements ServiceAdapterInterface
{
    /** The most people one import may carry */
    public const int BATCH = 1000;

    public function __construct(
        private Api $api,
    ) {}

    private static function subscribers(Binding $binding): string
    {
        return 'subscribers/' . rawurlencode($binding->list) . '.json';
    }

    /**
     * Deletes people from the list. A deleted subscriber isn't suppressed, so they can be added
     * again later.
     */
    #[Override]
    public function archive(Binding $binding, array $people): array
    {
        return array_map(
            /** @throws ConnectionRefused */
            fn(Person $person): Outcome => Outcomes::gone($this->api->send(
                'DELETE',
                self::subscribers($binding),
                query: ['email' => $person->email->value],
            )),
            $people,
        );
    }

    #[Override]
    public function capabilities(): Capabilities
    {
        return new Capabilities(self::BATCH, DoubleOptIn::PerList);
    }

    /**
     * The clients the API key reaches, for choosing which one's lists to keep: an agency's key
     * reaches each client it manages.
     *
     * @return list<Account>
     *
     * @throws ConnectionRefused
     */
    public function clients(): array
    {
        return array_map(
            /** @param array<array-key, mixed> $row */
            static fn(array $row): Account => new Account(
                Json::string($row, 'ClientID'),
                Json::string($row, 'Name'),
            ),
            Json::rows($this->read('clients.json')),
        );
    }

    /**
     * The list's custom fields, after the name and consent to track every list has.
     */
    #[Override]
    public function fields(string $listId): array
    {
        return [
            new RemoteField(Subscriber::NAME, 'Name', 'Text'),
            new RemoteField(Subscriber::CONSENT_TO_TRACK, 'Consent to track', 'Yes/No'),
            ...array_map(
                /** @param array<array-key, mixed> $row */
                static fn(array $row): RemoteField => new RemoteField(
                    trim(Json::string($row, 'Key'), characters: '[]'),
                    Json::string($row, 'FieldName'),
                    Json::string($row, 'DataType'),
                ),
                Json::rows($this->read('lists/' . rawurlencode($listId) . '/customfields.json')),
            ),
        ];
    }

    #[Override]
    public function lists(): array
    {
        return array_map(
            /** @param array<array-key, mixed> $row */
            static fn(array $row): RemoteList => new RemoteList(
                Json::string($row, 'ListID'),
                Json::string($row, 'Name'),
            ),
            Json::rows($this->read("{$this->client()}/lists.json")),
        );
    }

    /**
     * Imports people in two requests at most: those who agreed afresh with Resubscribe, so
     * someone who left can come back, and everyone else without it.
     */
    #[Override]
    public function subscribe(Binding $binding, array $people): array
    {
        $outcomes =
            $this->import(
                $binding,
                array_filter($people, static fn(Person $person): bool => $person->resubscribe),
                true,
            )
            + $this->import(
                $binding,
                array_filter($people, static fn(Person $person): bool => ! $person->resubscribe),
                false,
            );
        ksort($outcomes);

        return array_values($outcomes);
    }

    #[Override]
    public function unsubscribe(Binding $binding, array $people): array
    {
        return array_map(
            /** @throws ConnectionRefused */
            fn(Person $person): Outcome => Outcomes::gone($this->api->send(
                'POST',
                'subscribers/' . rawurlencode($binding->list) . '/unsubscribe.json',
                ['EmailAddress' => $person->email->value],
            )),
            $people,
        );
    }

    /**
     * Updates each person in place, never resubscribing them; Campaign Monitor adds no one this
     * way.
     */
    #[Override]
    public function update(Binding $binding, array $people): array
    {
        return array_map(
            /** @throws ConnectionRefused */
            fn(Person $person): Outcome => Outcomes::of($this->api->send(
                'PUT',
                self::subscribers($binding),
                [...Subscriber::body($binding, $person), 'Resubscribe' => false],
                ['email' => $person->email->value],
            )),
            $people,
        );
    }

    #[Override]
    public function verifyConnection(): Account
    {
        $details = Json::object($this->read("{$this->client()}.json"), 'BasicDetails');

        return new Account(Json::string($details, 'ClientID'), Json::string($details, 'CompanyName'));
    }

    private function client(): string
    {
        return 'clients/' . rawurlencode($this->api->connection->clientId);
    }

    /**
     * @param array<int, Person> $people
     *
     * @return array<int, Outcome> Keyed as $people is
     *
     * @throws ConnectionRefused
     */
    private function import(Binding $binding, array $people, bool $resubscribe): array
    {
        if ([] === $people) {
            return [];
        }

        $import = Import::of($this->api->send('POST', 'subscribers/' . rawurlencode($binding->list) . '/import.json', [
            'Subscribers'                            => array_values(array_map(
                /** @return array<string, mixed> */
                static fn(Person $person): array => Subscriber::body($binding, $person),
                $people,
            )),
            'Resubscribe'                            => $resubscribe,
            'QueueSubscriptionBasedAutoResponders'   => false,
            'RestartSubscriptionBasedAutoresponders' => false,
        ]));

        return array_map($import->outcome(...), $people);
    }

    /**
     * The body of a successful read, or why it can't be had.
     *
     * @throws ConnectionRefused
     */
    private function read(string $path): mixed
    {
        $reply = $this->api->send('GET', $path);
        if (! $reply->ok()) {
            throw ConnectionRefused::because($reply->message());
        }

        return $reply->body;
    }
}
