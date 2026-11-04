<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Answers from Campaign Monitor's API, as its documentation shows them.
 */
final class Replies
{
    /**
     * An error, with Campaign Monitor's code and message.
     *
     * @param array<string, string> $headers
     */
    public static function error(int $status, int $code, string $message, array $headers = []): ResponseInterface
    {
        return self::json($status, ['Code' => $code, 'Message' => $message], $headers);
    }

    /**
     * An import in which the people at $failures (email => [code, message]) weren't imported.
     *
     * @param array<string, array{int, string}> $failures
     */
    public static function import(array $failures = []): ResponseInterface
    {
        return self::json(201, [
            'FailureDetails'              => self::failureDetails($failures),
            'TotalUniqueEmailsSubmitted'  => 2,
            'TotalExistingSubscribers'    => 0,
            'TotalNewSubscribers'         => 2,
            'DuplicateEmailsInSubmission' => [],
        ]);
    }

    /**
     * The 400 Campaign Monitor gives when some of an import failed (code 210).
     *
     * @param array<string, array{int, string}> $failures
     */
    public static function importSomeFailed(array $failures): ResponseInterface
    {
        return self::json(400, [
            'Code'       => 210,
            'Message'    => 'Subscriber Import had some failures',
            'ResultData' => ['FailureDetails' => self::failureDetails($failures)],
        ]);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(int $status, mixed $body, array $headers = []): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', ...$headers],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    public static function ok(): ResponseInterface
    {
        return new Response(200);
    }

    /**
     * @param array<string, array{int, string}> $failures
     *
     * @return list<array{EmailAddress: string, Code: int, Message: string}>
     */
    private static function failureDetails(array $failures): array
    {
        $details = [];
        foreach ($failures as $email => [$code, $message]) {
            $details[] = ['EmailAddress' => $email, 'Code' => $code, 'Message' => $message];
        }

        return $details;
    }
}
