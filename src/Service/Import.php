<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Service;

use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\Person;

use function array_combine;
use function array_map;
use function strtolower;
use function trim;

/**
 * What an import did for each person in it. Campaign Monitor lists only the people it couldn't
 * import, so everyone else was imported, unless the whole request failed.
 *
 * @api
 */
final readonly class Import
{
    /** Some of the people sent couldn't be imported; ResultData says which */
    public const int SOME_FAILED = 210;

    /**
     * @param array<string, Outcome> $failures By email address
     */
    private function __construct(
        private Outcome $rest,
        private array $failures = [],
    ) {}

    public static function of(Reply $reply): self
    {
        if ($reply->ok()) {
            return new self(Outcome::done(), self::failures($reply->body));
        }

        return self::SOME_FAILED === $reply->code()
            ? new self(
                Outcome::done(),
                self::failures(Json::object($reply->body, 'ResultData')),
            )
            : new self(Outcomes::of($reply));
    }

    /**
     * @return array<string, Outcome>
     */
    private static function failures(mixed $result): array
    {
        $rows = Json::rows(Json::object($result, 'FailureDetails'));

        return array_combine(
            array_map(
                /** @param array<array-key, mixed> $row */
                static fn(array $row): string => strtolower(trim(Json::string($row, 'EmailAddress'))),
                $rows,
            ),
            array_map(
                /** @param array<array-key, mixed> $row */
                static fn(array $row): Outcome => Outcomes::code(
                    Json::int($row, 'Code'),
                    Json::string($row, 'Message'),
                ),
                $rows,
            ),
        );
    }

    public function outcome(Person $person): Outcome
    {
        return $this->failures[$person->email->value] ?? $this->rest;
    }
}
