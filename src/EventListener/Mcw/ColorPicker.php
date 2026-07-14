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
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Fritz Michael Gschwantner <fmg@inspiredminds.at>
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @copyright  2011 Andreas Schempp
 * @copyright  2011 certo web & design GmbH
 * @copyright  2013-2026 MEN AT WORK
 * @license    https://github.com/menatwork/contao-multicolumnwizard-bundle/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MenAtWork\MultiColumnWizardBundle\EventListener\Mcw;

use MenAtWork\MultiColumnWizardBundle\Event\GetColorPickerStringEvent;

/**
 * Class ColorPicker
 */
class ColorPicker
{
    /**
     * Generate the color picker markup.
     *
     * @param GetColorPickerStringEvent $event The event.
     *
     * @return void
     */
    public function executeEvent(GetColorPickerStringEvent $event)
    {
        // Get some vars.
        $fieldConfiguration = $event->getFieldConfiguration();
        $fieldId            = $event->getFieldId();

        // Support single fields as well (see #5240)
        $fieldId = isset($fieldConfiguration['eval']['multiple']) ? $fieldId . '_0' : $fieldId;

        // Contao 5 dropped MooRainbow in favour of the "contao--color-picker" Stimulus controller
        // (based on Pickr). Attach the controller to the field wrapper, flag the input as its target
        // and add the button target; the controller renders the picker and keeps the hex value in sync.
        $colorPicker = <<<HTML
<div data-contao--color-picker-target="button"></div>
<script>
  (function() {
    var input = document.getElementById("ctrl_$fieldId");
    if (!input || !input.parentNode) {
      return;
    }
    input.setAttribute("data-contao--color-picker-target", "input");
    var wrapper = input.parentNode;
    wrapper.setAttribute("data-contao--color-picker-theme-value", "monolith");
    var controllers = (wrapper.getAttribute("data-controller") || "").split(" ").filter(function(name) {
      return name !== "";
    });
    if (controllers.indexOf("contao--color-picker") === -1) {
      controllers.push("contao--color-picker");
      wrapper.setAttribute("data-controller", controllers.join(" "));
    }
  })();
</script>
HTML;

        $event->setColorPicker($colorPicker);
    }
}
