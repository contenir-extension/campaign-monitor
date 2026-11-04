<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Service;

use Contenir\Audience\Service\Outcome;

use function in_array;

/**
 * What became of a person, from Campaign Monitor's reply about them.
 *
 * @api
 */
final class Outcomes
{
    /** The address is on the client's suppression list, or was deleted: it won't be added back */
    public const array FORGOTTEN = [204, 205];

    /** The address isn't on the list */
    public const int NOT_IN_LIST = 203;

    /**
     * The outcome of an error Campaign Monitor gave for one person.
     */
    public static function code(?int $code, string $message): Outcome
    {
        return in_array($code, self::FORGOTTEN, strict: true)
            ? Outcome::forgotten($message)
            : Outcome::failed($message);
    }

    /**
     * The outcome of taking someone off the list: someone who isn't on it is already off it.
     */
    public static function gone(Reply $reply): Outcome
    {
        return self::NOT_IN_LIST === $reply->code() ? Outcome::done() : self::of($reply);
    }

    public static function of(Reply $reply): Outcome
    {
        if ($reply->ok()) {
            return Outcome::done();
        }

        return $reply->retriable()
            ? Outcome::retry($reply->message(), $reply->retryAfter)
            : self::code($reply->code(), $reply->message());
    }
}
