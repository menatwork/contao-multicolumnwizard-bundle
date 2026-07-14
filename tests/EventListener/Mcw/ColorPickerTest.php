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
 * @copyright  2011 Andreas Schempp
 * @copyright  2011 certo web & design GmbH
 * @copyright  2013-2019 MEN AT WORK
 * @license    https://github.com/menatwork/contao-multicolumnwizard-bundle/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MenAtWork\MultiColumnWizardBundle\Test\EventListener\Mcw;

use MenAtWork\MultiColumnWizardBundle\Event\GetColorPickerStringEvent;
use MenAtWork\MultiColumnWizardBundle\EventListener\Mcw\ColorPicker;
use PHPUnit\Framework\TestCase;

/**
 * This tests the color picker event listener.
 *
 * @covers \MenAtWork\MultiColumnWizardBundle\EventListener\Mcw\ColorPicker
 */
class ColorPickerTest extends TestCase
{
    public function testExecuteEvent()
    {
        $listener = new ColorPicker();

        $event = new GetColorPickerStringEvent('fieldId', 'tableName', [], 'fieldName');

        $listener->executeEvent($event);

        $this->assertSame(
            <<<HTML
<div data-contao--color-picker-target="button"></div>
<script>
  (function() {
    var input = document.getElementById("ctrl_fieldId");
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
HTML
            ,
            $event->getColorPicker()
        );
    }
}
