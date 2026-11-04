<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Service;

use Contenir\Extension\CampaignMonitor\Service\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;

#[Group('unit')]
final class ConnectionTest extends TestCase
{
    #[Test]
    public function signsInWithTheKeyAsTheBasicUser(): void
    {
        static::assertSame(
            'Basic ' . base64_encode('the-key:x'),
            (new Connection('the-key', 'client'))->authorization(),
        );
    }
}
