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
        $dcDriver->field = $fieldName;

        // Resolve the field configuration. For nested MCWs the posted name is a bracket path like
        // "base[1][sub]"; the configuration of "sub" then lives in the columnFields of "base", not
        // as a top level DCA field.
        $fieldConfig = $GLOBALS['TL_DCA'][$dcDriver->table]['fields'][$fieldName]
            ?? self::resolveNestedFieldConfig($fieldName, $dcDriver->table);

        // The field does not exist
        if (null === $fieldConfig) {
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

        $inputType = $fieldConfig['inputType'];

        /** @var string $widgetClassName */
        $widgetClassName = $GLOBALS['BE_FFL'][$inputType];

        /** @var MultiColumnWizard $widget */
        /** @var MultiColumnWizard $widgetClassName */
        $widget = new $widgetClassName(
            $widgetClassName::getAttributesFromDca(
                $fieldConfig,
                $dcDriver->inputName,
                '',
                $fieldName,
                $dcDriver->table,
                $dcDriver
            )
        );

        // Set some more information.
        $widget->currentRecord = $dcDriver->id;
        $widget->activeRecord  = $dcDriver->activeRecord;

        $event->setWidget($widget);
    }

    /**
     * Resolve the configuration of a (nested) MCW field from its posted name.
     *
     * For nested MCWs the posted field name is a bracket path like "base[1][sub]". The
     * configuration of "sub" lives in the columnFields of "base" (recursively); numeric segments
     * are row indices and carry no configuration. Returns null when the name is a plain top level
     * field (handled by the caller) or cannot be resolved.
     *
     * @param string $fieldName The posted (possibly bracketed) field name.
     * @param string $table     The table name.
     *
     * @return array|null The resolved field configuration or null.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private static function resolveNestedFieldConfig(string $fieldName, string $table): ?array
    {
        $segments = \preg_split('/[\[\]]+/', $fieldName, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($segments)) {
            return null;
        }

        $baseField = \array_shift($segments);
        if (!isset($GLOBALS['TL_DCA'][$table]['fields'][$baseField])) {
            return null;
        }

        $config    = $GLOBALS['TL_DCA'][$table]['fields'][$baseField];
        $descended = false;
        foreach ($segments as $segment) {
            // Row indices are numeric and carry no configuration.
            if (\is_numeric($segment)) {
                continue;
            }
            if (!isset($config['eval']['columnFields'][$segment])) {
                return null;
            }
            $config    = $config['eval']['columnFields'][$segment];
            $descended = true;
        }

        // Only return a result when we actually resolved a nested column field.
        return $descended ? $config : null;
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
        $env   = $dcGeneral->getEnvironment();
        $model = $dcGeneral->getModel() ?: $dcGeneral
            ->getEnvironment()
            ->getDataProvider()
            ->getEmptyModel();

        $dcgContaoWidgetManager = new ContaoWidgetManager($env, $model);

        $event->setWidget($dcgContaoWidgetManager->getWidget($fieldName));
    }
}
