<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Check;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\FixInterface;
use Contenir\Extension\Api\Diagnostic\FixResult;
use Contenir\Extension\CampaignMonitor\Sync\NotReady;
use Contenir\Extension\CampaignMonitor\Sync\PepperFile;
use Contenir\Extension\CampaignMonitor\Sync\Secrets;
use Override;
use Random\RandomException;

use function sprintf;

/**
 * The setup check's one-click fix for a missing pepper: makes one and saves it in the add-on's local config file,
 * where the CMS and the site both read it. A pepper already set is never replaced.
 *
 * @api
 */
final readonly class AddPepper implements FixInterface
{
    public function __construct(
        private Secrets $secrets = new Secrets(),
    ) {}

    /**
     * @throws RandomException When no pepper can be made.
     */
    #[Override]
    public function apply(array $values, ExtensionContextInterface $context): FixResult
    {
        $file = new PepperFile($context->sitePath(), $context->handle(), $this->secrets);
        try {
            $file->add();
        } catch (NotReady $e) {
            return FixResult::failed($e->getMessage());
        }

        return FixResult::applied(sprintf(
            'Made a pepper and saved it in %s. Keep that file when you deploy, and copy the pepper to any other'
                . ' environment that syncs the same lists.',
            $file->path(),
        ));
    }
}
