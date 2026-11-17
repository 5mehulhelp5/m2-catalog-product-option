<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOption\Test\Unit\Helper;

use FeWeDev\Base\Json;
use Infrangible\CatalogProductOption\Helper\Data;
use LogicException;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Option\Value;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Tests the price configuration of product options: prices with and without tax in the currency of the store, values
 * of options, and the event that lets other modules change the configuration.
 */
class DataTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $eventData = [];

    /**
     * Returns the helper with a currency rate of 2 and a tax rate of 10 %.
     *
     * @param array|null $changedConfig
     * @phpstan-param array<int|string, mixed>|null $changedConfig
     *
     * @return Data
     */
    private function createHelper(?array $changedConfig = null): Data
    {
        $catalogHelper = $this->createStub(CatalogHelper::class);
        $catalogHelper->method('getTaxPrice')->willReturnCallback(
            fn(Product $product, float $price, bool $includingTax): float => $includingTax ? $price * 1.1 : $price
        );

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('currency')->willReturnCallback(fn(float $value): float => $value * 2);

        $eventManager = $this->createStub(ManagerInterface::class);
        $eventManager->method('dispatch')->willReturnCallback(
            function (string $eventName, array $data) use ($changedConfig): void {
                $this->eventData = ['name' => $eventName] + $data;

                $configObj = $data[ 'configObj' ] ?? null;

                if ($changedConfig !== null && $configObj instanceof DataObject) {
                    $configObj->setData('config', $changedConfig);
                }
            }
        );

        return new Data($catalogHelper, $eventManager, new Json(), $pricingHelper);
    }

    /**
     * Sets the prices, price type and title of a stub of an option or option value.
     *
     * The prices are strings, as Magento loads them.
     *
     * @param Stub $priced
     * @param string $priceType
     * @param string $price
     * @param string $regularPrice
     * @param string $title
     * @param Product|null $product
     *
     * @return void
     */
    private function setPrices(
        Stub $priced,
        string $priceType,
        string $price,
        string $regularPrice,
        string $title,
        ?Product $product
    ): void {
        $priced->method('getProduct')->willReturn($product);
        $priced->method('getPriceType')->willReturn($priceType);
        $priced->method('getPrice')->willReturn($price);
        $priced->method('getRegularPrice')->willReturn($regularPrice);
        $priced->method('getTitle')->willReturn($title);
    }

    /**
     * Returns an option value.
     *
     * @param string $price
     * @param string $regularPrice
     * @param string $title
     * @param Product $product
     *
     * @return Value
     */
    private function createValue(string $price, string $regularPrice, string $title, Product $product): Value
    {
        $value = $this->createStub(Value::class);

        $this->setPrices($value, 'fixed', $price, $regularPrice, $title, $product);

        return $value;
    }

    /**
     * Returns an option without values, for example a text field.
     *
     * @param string $priceType
     *
     * @return Option
     */
    private function createOption(string $priceType = 'fixed'): Option
    {
        $option = $this->createStub(Option::class);

        $this->setPrices($option, $priceType, '10.0000', '12.0000', 'Engraving', $this->createStub(Product::class));

        $option->method('getId')->willReturn('5');
        $option->method('hasValues')->willReturn(false);

        return $option;
    }

    /**
     * Tests the prices of an option with a fixed price: converted into the currency of the store, the old and the final
     * price with tax, the base price without.
     *
     * @return void
     */
    public function testFixedPrice(): void
    {
        $config = $this->createHelper()->getOptionsJsonConfigData([$this->createOption()]);

        $this->assertEqualsWithDelta(
            [
                '5' => [
                    'prices' => [
                        'oldPrice'   => ['amount' => 26.4, 'adjustments' => []],
                        'basePrice'  => ['amount' => 20.0],
                        'finalPrice' => ['amount' => 22.0]
                    ],
                    'type'   => 'fixed',
                    'name'   => 'Engraving'
                ]
            ],
            $config,
            0.0001
        );
    }

    /**
     * Tests that percentage prices are not converted again: Magento calculates them from the converted product price.
     *
     * @return void
     */
    public function testPercentPrice(): void
    {
        $config = $this->createHelper()->getOptionsJsonConfigData([$this->createOption(Value::TYPE_PERCENT)]);

        $option = $config[ '5' ] ?? null;

        $this->assertIsArray($option);

        $prices = $option[ 'prices' ] ?? null;

        $this->assertIsArray($prices);
        $this->assertEqualsWithDelta(['amount' => 13.2, 'adjustments' => []], $prices[ 'oldPrice' ], 0.0001);
        $this->assertEqualsWithDelta(['amount' => 10.0], $prices[ 'basePrice' ], 0.0001);
        $this->assertEqualsWithDelta(['amount' => 11.0], $prices[ 'finalPrice' ], 0.0001);
    }

    /**
     * Tests that an option with values gets the configuration of each value by value ID.
     *
     * @return void
     */
    public function testOptionWithValues(): void
    {
        $product = $this->createStub(Product::class);

        $small = $this->createValue('1.0000', '1.0000', 'Small', $product);
        $large = $this->createValue('3.0000', '4.0000', 'Large', $product);

        $option = $this->createStub(Option::class);
        $option->method('getId')->willReturn('7');
        $option->method('hasValues')->willReturn(true);
        $option->method('getValues')->willReturn(['21' => $small, '22' => $large]);

        $config = $this->createHelper()->getOptionsJsonConfigData([$option]);

        $values = $config[ '7' ] ?? null;

        $this->assertIsArray($values);
        $this->assertSame([21, 22], array_keys($values));

        $largeConfig = $values[ 22 ];

        $this->assertIsArray($largeConfig);
        $this->assertSame('Large', $largeConfig[ 'name' ] ?? null);

        $prices = $largeConfig[ 'prices' ] ?? null;

        $this->assertIsArray($prices);
        $this->assertEqualsWithDelta(['amount' => 6.0], $prices[ 'basePrice' ] ?? null, 0.0001);
        $this->assertEqualsWithDelta(['amount' => 8.8, 'adjustments' => []], $prices[ 'oldPrice' ] ?? null, 0.0001);
    }

    /**
     * Tests that observers of the event get the configuration and the options, and can change the configuration.
     *
     * @return void
     */
    public function testEventChangesConfiguration(): void
    {
        $option = $this->createOption();

        $config = $this->createHelper(['5' => ['changed' => true]])->getOptionsJsonConfigData([$option]);

        $this->assertSame([5 => ['changed' => true]], $config);
        $this->assertSame('catalog_product_option_price_configuration_after', $this->eventData[ 'name' ] ?? null);
        $this->assertSame([$option], $this->eventData[ 'options' ] ?? null);
    }

    /**
     * Tests the configuration as JSON for the price script of the product page.
     *
     * @return void
     */
    public function testOptionsJsonConfig(): void
    {
        $json = $this->createHelper(['5' => ['type' => 'fixed']])->getOptionsJsonConfig([$this->createOption()]);

        $this->assertSame('{"5":{"type":"fixed"}}', $json);
    }

    /**
     * Tests that an option without product is a programming error.
     *
     * @return void
     */
    public function testOptionWithoutProduct(): void
    {
        $option = $this->createStub(Option::class);
        $option->method('hasValues')->willReturn(false);
        $option->method('getProduct')->willReturn(null);

        $this->expectException(LogicException::class);

        $this->createHelper()->getOptionsJsonConfigData([$option]);
    }
}
