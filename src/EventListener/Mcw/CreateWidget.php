<?php

/**
 * This file is part of menatwork/contao-multicolumnwizard-bundle.
 *
 * (c) 2012-2021 MEN AT WORK.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    menatwork/contao-multicolumnwizard-bundle
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright  2011 Andreas Schempp
 * @copyright  2011 certo web & design GmbH
 * @copyright  2013-2021 MEN AT WORK
 * @license    https://github.com/menatwork/contao-multicolumnwizard-bundle/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MenAtWork\MultiColumnWizardBundle\EventListener\Mcw;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Input;
use Contao\System;
use ContaoCommunityAlliance\DcGeneral\Contao\Compatibility\DcCompat;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ContaoWidgetManager;
use MenAtWork\MultiColumnWizardBundle\Contao\Widgets\MultiColumnWizard;
use MenAtWork\MultiColumnWizardBundle\Event\CreateWidgetEvent;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class CreateWidgetContao
 */
class CreateWidget
{
    /**
     * @var Logger
     */
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Create a widget for Contao context.
     *
     * @param CreateWidgetEvent $event The event.
     *
     * @return void
     *
     * @throws BadRequestHttpException When the field does not exist in the DCA.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function createWidgetContao(CreateWidgetEvent $event)
    {
        /** @var  DcCompat $dcGeneral */
        $dcDriver = $event->getDcDriver();

        // Check the context.
        if (($dcDriver instanceof DcCompat)) {
            return;
        }

        // Get the field name, handel editAll as well.
        $fieldName = $dcDriver->inputName = Input::post('name');
        if (Input::get('act') == 'editAll') {
            $fieldName = \preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $fieldName);
        }

        // A MCW in a MCW has a path.
        $path = \preg_split('/[\[|\]]/i', $fieldName);
        $path = array_filter($path, 'strlen');

        // The first should be the default MCW on the root level.
        if (count($path) > 1) {
            $fieldName = $path[0];
        }

        // The field does not exist
        if (!isset($GLOBALS['TL_DCA'][$dcDriver->table]['fields'][$fieldName])) {
            $this->logger->log(
                LogLevel::ERROR,
                'Field "' . $fieldName . '" does not exist in DCA "' . $dcDriver->table . '"',
                [
                    'contao' => new ContaoContext(
                        __CLASS__ . '::' . __FUNCTION__,
                        'MCW Execute Post Action'
                    )
                ]
            );
            throw new BadRequestHttpException('Bad request');
        }

        // MCW in MCW will generate a path, all else will be a array with one element.
        $this->loadDcaSettingInDepth(
            $dcDriver,
            $GLOBALS['TL_DCA'][$dcDriver->table]['fields'],
            $path,
            $event
        );
    }

    /**
     * @return void
     */
    private function loadDcaSettingInDepth($dcDriver, $dca, $path, $event)
    {
        $currentElement = $path[0];

        if (count($path) > 1) {
            if (array_key_exists($currentElement, $dca) && $dca[$currentElement]['inputType'] == 'multiColumnWizard') {
                $this->loadDcaSettingInDepth
                (
                    $dcDriver,
                    $dca[$currentElement]['eval']['columnFields'],
                    array_slice($path, 2),
                    $event
                );
                return;
            } else {
                // The path isn't a MCW element so someting is broken?
                throw new BadRequestHttpException('Bad request -- Got a MCW path, but can\'t be found');
            }
        } else {
            $mcwDca = $dca[$currentElement];
            $inputType = $mcwDca['inputType'];

            /** @var string $widgetClassName */
            $widgetClassName = $GLOBALS['BE_FFL'][$inputType];

            /** @var MultiColumnWizard $widget */
            /** @var MultiColumnWizard $widgetClassName */
            $widget = new $widgetClassName(
                $widgetClassName::getAttributesFromDca(
                    $mcwDca,
                    $currentElement,
                    '',
                    $fieldName,
                    $dcDriver->table,
                    $dcDriver
                )
            );

            // Set some more information.
            $widget->currentRecord = $dcDriver->id;
            $widget->activeRecord = $dcDriver->activeRecord;

            $event->setWidget($widget);
        }
    }

    /**
     * Create a widget for dc-general context.
     *
     * @param CreateWidgetEvent $event The event.
     *
     * @return void
     */
    public function createWidgetDcGeneral(CreateWidgetEvent $event)
    {
        /** @var  DcCompat $dcGeneral */
        $dcGeneral = $event->getDcDriver();

        // Check the context.
        if (!($dcGeneral instanceof DcCompat)) {
            return;
        }

        // Get the field name, handel editAll as well.
        $fieldName = Input::post('name');
        if (Input::get('act') == 'editAll') {
            $fieldName = \preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $fieldName);
        }

        // Trigger the dcg to generate the data.
        $env = $dcGeneral->getEnvironment();
        $model = $dcGeneral->getModel() ?: $dcGeneral
            ->getEnvironment()
            ->getDataProvider()
            ->getEmptyModel();

        $dcgContaoWidgetManager = new ContaoWidgetManager($env, $model);

        $event->setWidget($dcgContaoWidgetManager->getWidget($fieldName));
    }
}
