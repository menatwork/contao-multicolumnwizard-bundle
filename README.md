[![Build Status](https://github.com/menatwork/contao-multicolumnwizard-bundle/actions/workflows/diagnostics.yml/badge.svg)](https://github.com/menatwork/contao-multicolumnwizard-bundle/actions)
[![Latest Version tagged](http://img.shields.io/github/tag/menatwork/contao-multicolumnwizard-bundle.svg)](https://github.com/menatwork/contao-multicolumnwizard-bundle/tags)
[![Latest Version on Packagist](http://img.shields.io/packagist/v/menatwork/contao-multicolumnwizard-bundle.svg)](https://packagist.org/packages/menatwork/contao-multicolumnwizard-bundle)
[![Installations via composer per month](http://img.shields.io/packagist/dm/menatwork/contao-multicolumnwizard-bundle.svg)](https://packagist.org/packages/menatwork/contao-multicolumnwizard-bundle)

The repository of contao-multicolumnwizard-bundle was moved to [contao-community-alliance](https://github.com/contao-community-alliance/contao-multicolumnwizard-bundle). We plan to support
this version with bugfixes until the new version from the CCA is released.

# MultiColumnWizard

The MultiColumnWizard is a widget for mapping several fields of the same and/or different type (input type) in a
DCA element. The individual fields of the MCW are listed column by column in the backend and can be extended row by
row as a group. The arrangement corresponds to a multidimensional array of the form array[rows][fields], which is
stored in the database as a serialized array. The widget is almost identical to MultiTextWizard or MultiSelectWizard.
It extends the functionality of any widget.

More information can be found in the contao wiki
http://de.contaowiki.org/MultiColumnWizard

## Install

The Multicolumnwizard is usually installed via an extension. If it is necessary to install Multicolumnwizard yourself,
please use the console with the composer via the call

`composer require menatwork/contao-multicolumnwizard-bundle`

or

`web/contao-manager.phar.php composer require menatwork/contao-multicolumnwizard-bundle`

Developers should add the Multicolumnwizard to their `composer.json` as a dependent package.

## Usages

### Usage with columnFields

```php
<?php
$GLOBALS['TL_DCA']['tl_theme']['fields']['templateSelection'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_theme']['templateSelection'],
    'exclude'   => true,
    'inputType' => 'multiColumnWizard',
    'eval'      => [
        'columnFields' => [
            'ts_client_os'      => [
                'label'     => &$GLOBALS['TL_LANG']['tl_theme']['ts_client_os'],
                'exclude'   => true,
                'inputType' => 'select',
                'eval'      => [
                    'style'              => 'width:250px',
                    'includeBlankOption' => true,
                ],
                'options'   => [
                    'option1' => 'Option 1',
                    'option2' => 'Option 2',
                ],
            ],
            'ts_client_browser' => [
                'label'     => &$GLOBALS['TL_LANG']['tl_theme']['ts_client_browser'],
                'exclude'   => true,
                'inputType' => 'text',
                'eval'      => ['style' => 'width:180px'],
            ],
        ],
    ],
    'sql'       => 'blob NULL',
];
?>
```

### Usage with callback

```php
<?php
$GLOBALS['TL_DCA']['tl_table']['fields']['anything'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_table']['anything'],
    'exclude'   => true,
    'inputType' => 'multiColumnWizard',
    'eval'      => [
        'mandatory'       => true,
        'columnsCallback' => ['Class', 'Method'],
    ],
    'sql'       => 'blob NULL',
];
?>
```

### Disable Drag and Drop

```php
<?php
$GLOBALS['TL_DCA']['tl_theme']['fields']['templateSelection'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_theme']['templateSelection'],
    'exclude'   => true,
    'inputType' => 'multiColumnWizard',
    'eval'      => [
        // add this line for use the up and down arrows
        'dragAndDrop'  => false,
        'columnFields' => [
            'ts_client_browser' => [
                'label'     => &$GLOBALS['TL_LANG']['tl_theme']['ts_client_browser'],
                'exclude'   => true,
                'inputType' => 'text',
                'eval'      => ['style' => 'width:180px'],
            ],
        ],
    ],
    'sql'       => 'blob NULL',
];
?>
```

### Hide buttons

```php
<?php
$GLOBALS['TL_DCA']['tl_theme']['fields']['templateSelection'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_theme']['templateSelection'],
    'exclude'   => true,
    'inputType' => 'multiColumnWizard',
    'eval'      => [
        // add this line for hide one or all buttons
        'buttons'      =>
        [
            'new'    => false,
            'copy'   => false,
            'delete' => false,
            'up'     => false,
            'down'   => false,
            'move'   => false
        ],
        // as alternative to hide all buttons use the next line
        //'hideButtons'  => true,
        'columnFields' => [
            'ts_client_browser' => [
                'label'     => &$GLOBALS['TL_LANG']['tl_theme']['ts_client_browser'],
                'exclude'   => true,
                'inputType' => 'text',
                'eval'      => ['style' => 'width:180px'],
            ],
        ],
    ],
    'sql'       => 'blob NULL',
];
?>
```

### Other parameters

**From version 3.6.11:**

There is a new parameter `wrapper_style` in `eval` with which the enclosing element of a widget can be styled; in the
standard output this is a table cell `td`.

With the following specification:

```php
<?php
$GLOBALS['TL_DCA']['tl_theme']['fields']['templateSelection'] = [
    'inputType' => 'multiColumnWizard',
    //...
    'columnFields' => [
        'ts_client_browser' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_theme']['ts_client_browser'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => [
                'wrapper_style' => 'width:47%',
                'style'         => 'width:100%'
            ],
        ],
    ],
    //...
];
?>
```

this source code is generated:

```html
<td style="width:47%" class="hidelabel mcwUpdateFields">
  <input style="width:100%" type="text" name="mcwtest_default[0][ts_client_browser]" id="ctrl_mcwtest_default_row0_ts_client_browser" class="tl_text" value="" data-action="focus->contao--scroll-offset#store" data-contao--scroll-offset-target="autoFocus">
</td>
```

**From version 3.6.10:**

Use `default` in `eval` to set multiple default values and rows.

```php
<?php
$GLOBALS['TL_DCA']['tl_theme']['fields']['templateSelection'] = [
    'inputType' => 'multiColumnWizard',
    'eval'      => [
        'default'      => [
            ['ts_client_os' => 'option1', 'ts_client_browser' => 'FF'],
            ['ts_client_os' => 'option2', 'ts_client_browser' => 'Chrome'],
        ],
        'columnFields' => [
            'ts_client_os'      => [
                //...
            ],
            'ts_client_browser' => [
                //...
            ],
        ],
    ],
    'sql'       => 'blob NULL',
];
?>
```

The descriptions of the columns are displayed in the column header as a tooltip next to the icon `🛈` - the
character or text can be changed in the language file.

**From version 3.6.5:**

The Symfony translator can be used to translate the label and description. To do this, set the `useTranslator`
key to true.

```php
<?php
$GLOBALS['TL_DCA']['tl_theme']['fields']['anything'] = [
//...
    'inputType' => 'multiColumnWizard',
    'eval'      => [
        'useTranslator' => true,
// ...
    ],
];
?>
```
