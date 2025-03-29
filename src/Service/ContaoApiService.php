<?php

/**
 * This file is part of menatwork/contao-multicolumnwizard-bundle.
 *
 * (c) 2012-2019 MEN AT WORK.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    menatwork/contao-multicolumnwizard-bundle
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @copyright  2013-2023 MEN AT WORK
 * @license    https://github.com/menatwork/contao-multicolumnwizard-bundle/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MenAtWork\MultiColumnWizardBundle\Service;

use Composer\InstalledVersions;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;

class ContaoApiService
{
    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;

    /**
     * @var ScopeMatcher
     */
    private ScopeMatcher $scopeMatcher;

    /**
     * @var Packages
     */
    private Packages $packages;

    public function __construct(
        RequestStack $requestStack,
        ScopeMatcher $scopeMatcher,
        Packages     $packages
    ) {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->packages     = $packages;
    }

    /**
     * Check if we are in the BE mode.
     * Replacement for TL_MODE == 'BE'.
     *
     * @return bool
     */
    public function isBackend(): bool
    {
        // If we use the console this result in a return value of null.
        if (null === $this->requestStack->getCurrentRequest()) {
            return false;
        }

        return $this->scopeMatcher->isBackendRequest($this->requestStack->getCurrentRequest());
    }

    /**
     * Check if we are in the FE mode.
     * Replacement for TL_MODE == 'FE'.
     *
     * @return bool
     */
    public function isFrontend(): bool
    {
        // If we use the console this result in a return value of null.
        if (null === $this->requestStack->getCurrentRequest()) {
            return false;
        }

        return $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest());
    }

    /**
     * Get the version of the current Contao.
     *
     * @return string|null
     */
    public function getContaoVersion(): ?string
    {
        return InstalledVersions::getPrettyVersion('contao/core-bundle');
    }

    /**
     * Returns the public path for a file.
     *
     * @param string      $path
     * @param string|null $packageName
     *
     * @return string
     */
    public function getFileUrl(string $path, ?string $packageName = null): string
    {
        return $this->packages->getUrl($path, $packageName);
    }
}
