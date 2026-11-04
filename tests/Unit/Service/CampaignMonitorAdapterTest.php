<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Service;

use Contenir\Audience\Service\Account;
use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\RemoteField;
use Contenir\Audience\Service\RemoteList;
use Contenir\Extension\CampaignMonitor\Service\CampaignMonitorAdapter;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\People;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function array_map;
use function json_decode;

use const JSON_THROW_ON_ERROR;

#[Group('unit')]
final class CampaignMonitorAdapterTest extends TestCase
{
    /**
     * @return array{string, string, mixed}
     */
    private static function sent(RequestInterface $request): array
    {
        $body = (string) $request->getBody();

        return [
            $request->getMethod(),
            (string) $request->getUri(),
            '' === $body ? null : json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR),
        ];
    }

    #[Test]
    public function answersEachPersonInTheOrderGiven(): void
    {
        $http = new FakeHttpClient(
            Replies::import(['grace@example.com' => [204, 'In Suppression List']]),
            Replies::import(['alan@example.com' => [1, 'Invalid Email Address']]),
        );
        $outcomes = (new CampaignMonitorAdapter(Apis::over($http)))->subscribe(People::binding(), [
            People::person('ada@example.com'),
            People::person('grace@example.com', resubscribe: true),
            People::person('alan@example.com'),
        ]);

        static::assertEquals(
            [Outcome::done(), Outcome::forgotten('In Suppression List'), Outcome::failed('Invalid Email Address')],
            $outcomes,
        );
    }

    #[Test]
    public function deletesEachPersonToArchiveThem(): void
    {
        $http     = new FakeHttpClient(Replies::ok(), Replies::error(500, 500, 'Server error'));
        $outcomes = (new CampaignMonitorAdapter(Apis::over($http)))->archive(People::binding(), [
            People::person(),
            People::person('grace@example.com'),
        ]);

        static::assertEquals(
            [
                [
                    'DELETE',
                    'https://api.createsend.com/api/v3.3/subscribers/' . People::LIST . '.json?email=ada%40example.com',
                    null,
                ],
                [Outcome::done(), Outcome::retry('Server error')],
            ],
            [self::sent($http->requests[0]), $outcomes],
        );
    }

    #[Test]
    public function importsAThousandAtATimeAndLeavesConfirmationToTheList(): void
    {
        $adapter = new CampaignMonitorAdapter(Apis::over(new FakeHttpClient()));

        static::assertEquals(new Capabilities(1000, DoubleOptIn::PerList), $adapter->capabilities());
    }

    #[Test]
    public function importsPeopleWithoutResubscribingOrStartingAutoresponders(): void
    {
        $http = new FakeHttpClient(Replies::import());
        (new CampaignMonitorAdapter(Apis::over($http)))->subscribe(People::binding(), [People::person()]);

        static::assertSame(
            [
                'POST',
                'https://api.createsend.com/api/v3.3/subscribers/' . People::LIST . '/import.json',
                [
                    'Subscribers'                            => [
                        ['EmailAddress' => 'ada@example.com', 'CustomFields' => [], 'ConsentToTrack' => 'No'],
                    ],
                    'Resubscribe'                            => false,
                    'QueueSubscriptionBasedAutoResponders'   => false,
                    'RestartSubscriptionBasedAutoresponders' => false,
                ],
            ],
            self::sent($http->requests[0]),
        );
    }

    #[Test]
    public function listsTheClientsLists(): void
    {
        $http  = new FakeHttpClient(Replies::json(200, [['ListID' => 'l1', 'Name' => 'Newsletter']]));
        $lists = (new CampaignMonitorAdapter(Apis::over($http)))->lists();

        static::assertEquals(
            [
                'https://api.createsend.com/api/v3.3/clients/' . Apis::CLIENT . '/lists.json',
                [new RemoteList('l1', 'Newsletter')],
            ],
            [(string) $http->requests[0]->getUri(), $lists],
        );
    }

    #[Test]
    public function listsTheClientsTheKeyReaches(): void
    {
        $http = new FakeHttpClient(Replies::json(200, [
            ['ClientID' => 'c1', 'Name' => 'Studio Esteta'],
            ['ClientID' => 'c2', 'Name' => 'Sector7g'],
        ]));
        $clients = (new CampaignMonitorAdapter(Apis::over($http)))->clients();

        static::assertEquals(
            [
                'https://api.createsend.com/api/v3.3/clients.json',
                [new Account('c1', 'Studio Esteta'), new Account('c2', 'Sector7g')],
            ],
            [(string) $http->requests[0]->getUri(), $clients],
        );
    }

    #[Test]
    public function listsTheNameConsentAndEachCustomField(): void
    {
        $http = new FakeHttpClient(Replies::json(200, [
            ['FieldName' => 'Website', 'Key' => '[website]', 'DataType' => 'Text', 'FieldOptions' => []],
        ]));
        $fields = (new CampaignMonitorAdapter(Apis::over($http)))->fields('l/1');

        static::assertEquals(
            [
                'https://api.createsend.com/api/v3.3/lists/l%2F1/customfields.json',
                [
                    new RemoteField('Name', 'Name', 'Text'),
                    new RemoteField('ConsentToTrack', 'Consent to track', 'Yes/No'),
                    new RemoteField('website', 'Website', 'Text'),
                ],
            ],
            [(string) $http->requests[0]->getUri(), $fields],
        );
    }

    #[Test]
    public function refusesAReadThatFailed(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: Campaign Monitor answered 404');

        (new CampaignMonitorAdapter(Apis::over(new FakeHttpClient(Replies::json(404, null)))))->lists();
    }

    #[Test]
    public function resubscribesOnlyThoseWhoAgreedAfresh(): void
    {
        $http = new FakeHttpClient(Replies::import(), Replies::import());
        (new CampaignMonitorAdapter(Apis::over($http)))->subscribe(People::binding(), [
            People::person('ada@example.com'),
            People::person('grace@example.com', resubscribe: true),
            People::person('alan@example.com'),
        ]);

        static::assertSame(
            [[true, ['grace@example.com']], [false, ['ada@example.com', 'alan@example.com']]],
            array_map(
                static function (RequestInterface $request): array {
                    /** @var array{Resubscribe: bool, Subscribers: list<array{EmailAddress: string}>} $body */
                    $body = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);

                    return [
                        $body['Resubscribe'],
                        array_map(static fn(array $row): string => $row['EmailAddress'], $body['Subscribers']),
                    ];
                },
                $http->requests,
            ),
        );
    }

    #[Test]
    public function sendsNoImportForAnEmptyBatch(): void
    {
        $http = new FakeHttpClient();

        static::assertSame([[], []], [
            (new CampaignMonitorAdapter(Apis::over($http)))->subscribe(People::binding(), []),
            $http->requests,
        ]);
    }

    #[Test]
    public function unsubscribesEachPerson(): void
    {
        $http     = new FakeHttpClient(Replies::ok(), Replies::error(400, 203, 'Subscriber not in list'));
        $outcomes = (new CampaignMonitorAdapter(Apis::over($http)))->unsubscribe(People::binding(), [
            People::person(),
            People::person('grace@example.com'),
        ]);

        static::assertEquals(
            [
                [
                    'POST',
                    'https://api.createsend.com/api/v3.3/subscribers/' . People::LIST . '/unsubscribe.json',
                    ['EmailAddress' => 'ada@example.com'],
                ],
                [Outcome::done(), Outcome::done()],
            ],
            [self::sent($http->requests[0]), $outcomes],
        );
    }

    #[Test]
    public function updatesEachPersonInPlaceWithoutResubscribingThem(): void
    {
        $http     = new FakeHttpClient(Replies::ok(), Replies::error(400, 203, 'Subscriber not in list'));
        $outcomes = (new CampaignMonitorAdapter(Apis::over($http)))->update(People::binding(), [
            People::person(values: ['Name' => 'Ada']),
            People::person('grace@example.com'),
        ]);

        static::assertEquals(
            [
                [
                    'PUT',
                    'https://api.createsend.com/api/v3.3/subscribers/' . People::LIST . '.json?email=ada%40example.com',
                    [
                        'EmailAddress'   => 'ada@example.com',
                        'CustomFields'   => [],
                        'ConsentToTrack' => 'No',
                        'Name'           => 'Ada',
                        'Resubscribe'    => false,
                    ],
                ],
                [Outcome::done(), Outcome::failed('Subscriber not in list')],
            ],
            [self::sent($http->requests[0]), $outcomes],
        );
    }

    #[Test]
    public function verifiesTheConnectionByReadingTheClient(): void
    {
        $http = new FakeHttpClient(Replies::json(200, [
            'BasicDetails' => ['ClientID' => Apis::CLIENT, 'CompanyName' => 'Studio Esteta'],
        ]));
        $account = (new CampaignMonitorAdapter(Apis::over($http)))->verifyConnection();

        static::assertEquals(
            [
                'https://api.createsend.com/api/v3.3/clients/' . Apis::CLIENT . '.json',
                new Account(Apis::CLIENT, 'Studio Esteta'),
            ],
            [(string) $http->requests[0]->getUri(), $account],
        );
    }
}
