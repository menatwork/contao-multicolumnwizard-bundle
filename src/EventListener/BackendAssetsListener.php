<?php

/**
 * This file is part of menatwork/contao-multicolumnwizard-bundle.
 *
 * (c) 2012-2026 MEN AT WORK.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    menatwork/contao-multicolumnwizard-bundle
 * @author     Marko Cupic <m.cupic@gmx.ch>
 * @copyright  2011 Andreas Schempp
 * @copyright  2011 certo web & design GmbH
 * @copyright  2013-2026 MEN AT WORK
 * @license    https://github.com/menatwork/contao-multicolumnwizard-bundle/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MenAtWork\MultiColumnWizardBundle\EventListener;

use MenAtWork\MultiColumnWizardBundle\Service\ContaoApiService;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Adds the back end assets (JS and CSS) of the MultiColumnWizard.
 *
 * The assets are added on kernel.request with the default priority, i.e. after the routing has set the
 * request scope and before Contao backs up the legacy asset globals. Contao 5.7 backs up
 * $GLOBALS['TL_JAVASCRIPT'] and $GLOBALS['TL_CSS'] at the end of kernel.request
 * (LegacyGlobalsBackupListener, priority -1024) and restores them in
 * ResponseContextAccessor::setResponseContext(). Assets added in the initializeSystem hook can therefore
 * get lost, depending on when the Contao framework is initialized, which results in
 * "MultiColumnWizard is not defined" in the back end.
 */
class BackendAssetsListener
{
    /**
     * @param ContaoApiService $contaoApi
     * @param bool             $kernelDebug
     */
    public function __construct(
        private readonly ContaoApiService $contaoApi,
        private readonly bool $kernelDebug
    ) {
    }

    /**
     * Add the JS and the CSS in the back end.
     *
     * @param RequestEvent $event
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->contaoApi->isBackend()) {
            return;
        }

        $GLOBALS['TL_JAVASCRIPT']['multicolumnwizard'] = $this->kernelDebug
            ? 'bundles/multicolumnwizard/js/multicolumnwizard_be_src.js'
            : 'bundles/multicolumnwizard/js/multicolumnwizard_be.js';

        $GLOBALS['TL_CSS']['multicolumnwizard'] = $this->kernelDebug
            ? 'bundles/multicolumnwizard/css/multicolumnwizard_src.css'
            : 'bundles/multicolumnwizard/css/multicolumnwizard.css';
    }
}
