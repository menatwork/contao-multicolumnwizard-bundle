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
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @copyright  2013-2026 MEN AT WORK
 * @license    https://github.com/menatwork/contao-multicolumnwizard-bundle/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace MenAtWork\MultiColumnWizardBundle\Api\Widget;

use Contao\ApiBundle\DataContainer\DataContainerFieldContext;
use Contao\ApiBundle\DataContainer\DataContainerRelationDefinitionFactory;
use Contao\ApiBundle\Relation\RelationResolverInterface;
use Contao\ApiBundle\Schema\DataContainerSchemaFactory;
use Contao\ApiBundle\Widget\WidgetConverterInterface;
use Contao\ApiBundle\Widget\WidgetConverterRegistry;
use Contao\StringUtil;
use MenAtWork\MultiColumnWizardBundle\Contao\Widgets\MultiColumnWizard;

/**
 * Describes a multi column wizard to the Contao API as a list of objects, one property for each column.
 *
 * The columns are converted by the converters of their own widgets. A wizard is only supported when all of its columns
 * are: a row is replaced as a whole, a column that is left out would lose its values. Wizards with columns from a
 * callback (columnsCallback) are not known before the widget exists, they stay out of the API.
 *
 * @SuppressWarnings(PHPMD.Superglobals)
 */
final class MultiColumnWizardConverter implements WidgetConverterInterface
{
    public function __construct(
        private readonly WidgetConverterRegistry $converters,
        private readonly DataContainerSchemaFactory $schemaFactory,
        private readonly RelationResolverInterface $relationResolver,
        private readonly DataContainerRelationDefinitionFactory $relationDefinitionFactory,
    ) {
    }

    public function supports(array $config): bool
    {
        $widget = $GLOBALS['BE_FFL'][$config['inputType'] ?? ''] ?? null;
        if (!\is_string($widget) || !is_a($widget, MultiColumnWizard::class, true)) {
            return false;
        }

        $columns = $this->getColumns($config);
        if ([] === $columns || isset($config['eval']['columnsCallback'])) {
            return false;
        }

        return !array_any($columns, fn (array $column): bool => !$this->converters->get($column));
    }

    public function getSchema(array $config, array $schema): array
    {
        $properties = [];
        $readOnly   = false;
        foreach ($this->getColumns($config) as $name => $column) {
            $properties[$name] = $this->schemaFactory->createWidgetSchema($column);
            $readOnly          = $readOnly || ($properties[$name]['readOnly'] ?? false);
        }

        $schema = ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false]];
        if ([] !== $properties) {
            $schema['items']['properties'] = $properties;
        }
        if (($min = (int) ($config['eval']['minCount'] ?? 0)) > 0) {
            $schema['minItems'] = $min;
        }
        if (($max = (int) ($config['eval']['maxCount'] ?? 0)) > 0) {
            $schema['maxItems'] = $max;
        }
        // Read-only columns cannot be preserved when rows are added, removed or reordered.
        if ($readOnly) {
            $schema['readOnly'] = true;
        }

        return $schema;
    }

    public function convertToApiValue(mixed $value, array $config, array $schema): array
    {
        $rows = match (true) {
            null === $value || '' === $value => [],
            !\is_string($value)               => $value,
            'json' === ($config['sql']['type'] ?? null) => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
            default                           => StringUtil::deserialize($value, true),
        };

        return array_map(
            function ($row) use ($config, $schema): object {
                $row       = (array) $row;
                $converted = [];
                foreach ($this->getColumns($config) as $name => $column) {
                    $property = $schema['items']['properties'][$name] ?? [];
                    if ($property['writeOnly'] ?? false) {
                        continue;
                    }

                    $converter = $this->converters->get($column);
                    $value     = $converter?->convertToApiValue($row[$name] ?? null, $column, $property);
                    $relation  = $this->relationDefinitionFactory->create(new DataContainerFieldContext($column));

                    $converted[$name] = $relation
                        ? $this->relationResolver->resolveToReference($value, $relation)
                        : $value;
                }

                return (object) $converted;
            },
            array_values($rows)
        );
    }

    public function convertToFormValue(mixed $value, array $config, array $schema): array
    {
        $converted = [];
        foreach (array_values((array) $value) as $index => $row) {
            $row = (array) $row;
            $converted[$index] = [];
            foreach ($this->getColumns($config) as $name => $column) {
                $property = $schema['items']['properties'][$name] ?? [];
                if ($property['readOnly'] ?? false) {
                    continue;
                }

                $converter = $this->converters->get($column);
                $cell      = $row[$name] ?? null;
                $relation  = $this->relationDefinitionFactory->create(new DataContainerFieldContext($column));
                $cell      = $relation ? $this->relationResolver->resolveToIdentifier($cell, $relation) : $cell;

                $converted[$index][$name] = $converter?->convertToFormValue($cell, $column, $property);
            }
        }

        return $converted;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getColumns(array $config): array
    {
        return $config['eval']['columnFields'] ?? [];
    }
}
