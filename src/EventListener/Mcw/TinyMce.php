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
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @copyright  2011 Andreas Schempp
 * @copyright  2011 certo web & design GmbH
 * @copyright  2013-2019 MEN AT WORK
 * @license    https://github.com/menatwork/contao-multicolumnwizard-bundle/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MenAtWork\MultiColumnWizardBundle\EventListener\Mcw;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\System;
use MenAtWork\MultiColumnWizardBundle\Event\GetTinyMceStringEvent;
use MenAtWork\MultiColumnWizardBundle\Service\ContaoApiService;

/**
 * Class TinyMce
 */
class TinyMce
{
    private ContaoApiService $contaoApi;

    public function __construct(ContaoApiService $contaoApi)
    {
        $this->contaoApi = $contaoApi;
    }

    /**
     * Generate the TinyMce Script.
     *
     * @param GetTinyMceStringEvent $event The event.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function executeEvent(GetTinyMceStringEvent $event)
    {
        // Get some vars.
        $field   = $event->getFieldConfiguration();
        $table   = $event->getTableName();
        $fieldId = $event->getFieldId();

        list ($file, $type) = explode('|', $field['eval']['rte'] ?? '') + [null, null];

        $fileBrowserTypes = [];
        // Since we don't know if this is the right call for other versions of contao
        // we won't use dependencies injection.
        $pickerBuilder = System::getContainer()->get('contao.picker.builder');

        foreach (array('file' => 'image', 'link' => 'file') as $context => $fileBrowserType) {
            if ($pickerBuilder->supportsContext($context)) {
                $fileBrowserTypes[] = $fileBrowserType;
            }
        }

        /** @var BackendTemplate|object $objTemplate */
        $objTemplate           = new BackendTemplate('be_' . $file);
        $objTemplate->selector = 'ctrl_' . $fieldId;
        $objTemplate->source   = $table . '.' . $fieldId;

        if (version_compare($this->contaoApi->getContaoVersion(), '5.0', '>=')) {
            // Contao 5: editor is attached via the contao--tinymce Stimulus controller.
            // fileBrowserTypes must be space-separated; additional template vars are required.
            $objTemplate->fileBrowserTypes = implode(' ', $fileBrowserTypes);
            $objTemplate->theme            = Backend::getTheme();
            $objTemplate->enableTinyMce    = $GLOBALS['TL_CONFIG']['useRTE'] ?? false;
            $objTemplate->tinyMceLanguage  = Backend::getTinyMceLanguage();
            $objTemplate->readonly         = (bool) ($field['eval']['readonly'] ?? false);
        } else {
            // Contao 4: classic $GLOBALS['TL_RTE'] mechanism.
            // fileBrowserTypes must be comma-separated; language param still needed.
            $objTemplate->type             = $type;
            $objTemplate->fileBrowserTypes = implode(',', $fileBrowserTypes);
            // Deprecated since Contao 4.0, to be removed in Contao 5.0
            $objTemplate->language         = Backend::getTinyMceLanguage();
        }

        $event->setTinyMce($objTemplate->parse());
    }
}
