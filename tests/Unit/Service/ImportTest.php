<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Service;

use Contenir\Audience\Service\Outcome;
use Contenir\Extension\CampaignMonitor\Service\Import;
use Contenir\Extension\CampaignMonitor\Service\Reply;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\People;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ImportTest extends TestCase
{
    #[Test]
    public function failsSomeoneCampaignMonitorListedAsFailing(): void
    {
        $import = Import::of(new Reply(201, [
            'FailureDetails' => [
                ['EmailAddress' => ' Ada@Example.com', 'Code' => 1, 'Message' => 'Invalid Email Address'],
            ],
        ]));

        static::assertEquals(Outcome::failed('Invalid Email Address'), $import->outcome(People::person()));
    }

    #[Test]
    public function givesEveryoneTheOutcomeOfARequestThatFailedWhole(): void
    {
        $import = Import::of(new Reply(503, retryAfter: 60));

        static::assertEquals(Outcome::retry('Campaign Monitor answered 503', 60), $import->outcome(People::person()));
    }

    #[Test]
    public function importsEveryoneCampaignMonitorDidntListAsFailing(): void
    {
        $import = Import::of(new Reply(201, ['FailureDetails' => [], 'TotalNewSubscribers' => 1]));

        static::assertEquals(Outcome::done(), $import->outcome(People::person()));
    }

    #[Test]
    public function readsTheFailuresFromAnImportThatPartlyFailed(): void
    {
        $import = Import::of(new Reply(400, [
            'Code'       => 210,
            'Message'    => 'Subscriber Import had some failures',
            'ResultData' => [
                'FailureDetails' => [
                    ['EmailAddress' => 'ada@example.com', 'Code' => 204, 'Message' => 'In Suppression List'],
                ],
            ],
        ]));

        static::assertEquals(
            [Outcome::forgotten('In Suppression List'), Outcome::done()],
            [$import->outcome(People::person()), $import->outcome(People::person('grace@example.com'))],
        );
    }
}
