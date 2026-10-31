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
declare(strict_types=1);

namespace MenAtWork\MultiColumnWizardBundle\Test\Api\Widget;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Contao\ApiBundle\DataContainer\DataContainerRelationDefinitionFactory;
use Contao\ApiBundle\Relation\RelationResolverInterface;
use Contao\ApiBundle\Schema\DataContainerSchemaFactory;
use Contao\ApiBundle\Widget\WidgetConverterInterface;
use Contao\ApiBundle\Widget\WidgetConverterRegistry;
use Contao\CoreBundle\DataContainer\ForeignKeyParser;
use Contao\CoreBundle\Framework\ContaoFramework;
use MenAtWork\MultiColumnWizardBundle\Api\Widget\MultiColumnWizardConverter;
use MenAtWork\MultiColumnWizardBundle\Contao\Widgets\MultiColumnWizard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

#[CoversClass(MultiColumnWizardConverter::class)]
final class MultiColumnWizardConverterTest extends TestCase
{
    private array $befFfl;

    protected function setUp(): void
    {
        if (!interface_exists(WidgetConverterInterface::class)) {
            self::markTestSkipped('The Contao API bundle is not installed.');
        }

        $this->befFfl                      = $GLOBALS['BE_FFL'] ?? [];
        $GLOBALS['BE_FFL']['multiColumnWizard'] = MultiColumnWizard::class;
    }

    protected function tearDown(): void
    {
        $GLOBALS['BE_FFL'] = $this->befFfl;
    }

    public function testSupportsAWizardWhoseColumnsAreAllSupported(): void
    {
        self::assertTrue($this->converter()->supports($this->config()));
    }

    public function testDoesNotSupportOtherWidgets(): void
    {
        self::assertFalse($this->converter()->supports(['inputType' => 'text']));
    }

    public function testDoesNotSupportAWizardWithoutColumns(): void
    {
        self::assertFalse($this->converter()->supports(['inputType' => 'multiColumnWizard', 'eval' => []]));
    }

    public function testDoesNotSupportColumnsFromACallback(): void
    {
        $config = $this->config();
        $config['eval']['columnsCallback'] = ['Some\\Class', 'columns'];

        self::assertFalse($this->converter()->supports($config));
    }

    public function testDoesNotSupportAColumnWithoutConverter(): void
    {
        $config = $this->config();
        $config['eval']['columnFields']['extra'] = ['inputType' => 'unknown'];

        self::assertFalse($this->converter()->supports($config));
    }

    public function testDescribesTheRowsAndTheirLimits(): void
    {
        $config = $this->config();
        $config['eval']['minCount'] = 2;
        $config['eval']['maxCount'] = 5;

        $schema = $this->converter()->getSchema($config, []);

        self::assertSame('array', $schema['type']);
        self::assertSame(2, $schema['minItems']);
        self::assertSame(5, $schema['maxItems']);
        self::assertFalse($schema['items']['additionalProperties']);
        self::assertSame(['key', 'value'], array_keys($schema['items']['properties']));
        self::assertArrayNotHasKey('readOnly', $schema);
    }

    public function testAReadOnlyColumnMakesTheWholeWizardReadOnly(): void
    {
        $config = $this->config();
        $config['eval']['columnFields']['value']['eval']['readonly'] = true;

        self::assertTrue($this->converter()->getSchema($config, [])['readOnly']);
    }

    public function testConvertsStoredRowsToObjects(): void
    {
        $config = $this->config();
        $schema = $this->converter()->getSchema($config, []);

        $value = $this->converter()->convertToApiValue(
            serialize([['key' => 'a', 'value' => '1'], ['key' => 'b']]),
            $config,
            $schema
        );

        self::assertEquals([(object) ['key' => 'a', 'value' => '1'], (object) ['key' => 'b', 'value' => null]], $value);
    }

    public function testConvertsAnEmptyValueToNoRows(): void
    {
        $config = $this->config();
        $schema = $this->converter()->getSchema($config, []);

        self::assertSame([], $this->converter()->convertToApiValue(null, $config, $schema));
        self::assertSame([], $this->converter()->convertToApiValue('', $config, $schema));
    }

    public function testReadsJsonStorage(): void
    {
        $config = $this->config();
        $config['sql'] = ['type' => 'json'];
        $schema = $this->converter()->getSchema($config, []);

        $value = $this->converter()->convertToApiValue('[{"key":"a","value":"1"}]', $config, $schema);

        self::assertEquals([(object) ['key' => 'a', 'value' => '1']], $value);
    }

    public function testBuildsTheFormValueByRowAndColumn(): void
    {
        $config = $this->config();
        $schema = $this->converter()->getSchema($config, []);

        $value = $this->converter()->convertToFormValue(
            [(object) ['key' => 'a', 'value' => '1'], ['key' => 'b', 'value' => '2']],
            $config,
            $schema
        );

        self::assertSame([['key' => 'a', 'value' => '1'], ['key' => 'b', 'value' => '2']], $value);
    }

    public function testLeavesReadOnlyColumnsOutOfTheFormValue(): void
    {
        $config = $this->config();
        $config['eval']['columnFields']['value']['eval']['readonly'] = true;
        $schema = $this->converter()->getSchema($config, []);

        $value = $this->converter()->convertToFormValue([['key' => 'a', 'value' => '1']], $config, $schema);

        self::assertSame([['key' => 'a']], $value);
    }

    private function config(): array
    {
        return [
            'inputType' => 'multiColumnWizard',
            'eval'      => [
                'columnFields' => [
                    'key'   => ['inputType' => 'text', 'eval' => ['mandatory' => true]],
                    'value' => ['inputType' => 'text'],
                ],
            ],
        ];
    }

    private function converter(): MultiColumnWizardConverter
    {
        $registry = new WidgetConverterRegistry([$this->textConverter()]);
        $resolver = $this->createStub(RelationResolverInterface::class);
        $relations = new DataContainerRelationDefinitionFactory(
            $this->createStub(ForeignKeyParser::class),
            $registry,
            $this->createStub(ResourceMetadataCollectionFactoryInterface::class)
        );
        $schemas = new DataContainerSchemaFactory(
            $this->createStub(ContaoFramework::class),
            $registry,
            $resolver,
            $this->createStub(LocaleSwitcher::class),
            $relations
        );

        return new MultiColumnWizardConverter($registry, $schemas, $resolver, $relations);
    }

    /**
     * Stands in for the converter of the core, which handles the text widget.
     */
    private function textConverter(): WidgetConverterInterface
    {
        return new class implements WidgetConverterInterface {
            public function supports(array $config): bool
            {
                return 'text' === ($config['inputType'] ?? null);
            }

            public function getSchema(array $config, array $schema): array
            {
                return ['type' => 'string'] + $schema;
            }

            public function convertToApiValue(mixed $value, array $config, array $schema): mixed
            {
                return null === $value ? null : (string) $value;
            }

            public function convertToFormValue(mixed $value, array $config, array $schema): mixed
            {
                return null === $value ? '' : (string) $value;
            }
        };
    }
}
