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
     * The client's details, as GET clients/{id}.json gives them.
     */
    public static function client(string $name = 'Studio Esteta'): ResponseInterface
    {
        return self::json(200, ['BasicDetails' => ['ClientID' => Apis::CLIENT, 'CompanyName' => $name]]);
    }

    /**
     * The clients a key reaches, as GET clients.json gives them: name by id.
     *
     * @param array<string, string> $clients
     */
    public static function clients(array $clients): ResponseInterface
    {
        $rows = [];
        foreach ($clients as $id => $name) {
            $rows[] = ['ClientID' => $id, 'Name' => $name];
        }

        return self::json(200, $rows);
    }

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

    /**
     * A client's lists, as GET clients/{id}/lists.json gives them: name by id.
     *
     * @param array<string, string> $lists
     */
    public static function lists(array $lists): ResponseInterface
    {
        $rows = [];
        foreach ($lists as $id => $name) {
            $rows[] = ['ListID' => $id, 'Name' => $name];
        }

        return self::json(200, $rows);
    }

    public static function ok(): ResponseInterface
    {
        return new Response(200);
    }

    /**
     * The 401 Campaign Monitor gives for a key it doesn't know.
     */
    public static function refused(): ResponseInterface
    {
        return self::error(401, 50, 'Must supply a valid HTTP Basic Authorization header');
    }

    /**
     * A list's webhooks, as GET lists/{id}/webhooks.json gives them: status by id.
     *
     * @param array<string, string> $webhooks
     */
    public static function webhooks(array $webhooks): ResponseInterface
    {
        $rows = [];
        foreach ($webhooks as $id => $status) {
            $rows[] = ['WebhookID' => $id, 'Events' => ['Deactivate'], 'Url' => 'https://x', 'Status' => $status];
        }

        return self::json(200, $rows);
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
