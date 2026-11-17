<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOption\Helper;

use FeWeDev\Base\Json;
use LogicException;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Option\Value;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

/**
 * Builds the price configuration of product options that the price script of the product page uses, like Magento's
 * block of product options does.
 */
class Data
{
    /**
     * @param CatalogHelper $catalogHelper
     * @param ManagerInterface $eventManager
     * @param Json $json
     * @param PricingHelper $pricingHelper
     */
    public function __construct(
        protected CatalogHelper $catalogHelper,
        protected ManagerInterface $eventManager,
        protected Json $json,
        protected PricingHelper $pricingHelper
    ) {
    }

    /**
     * Returns the price configuration of the options as JSON.
     *
     * @param Option[] $options
     * @phpstan-param array<int|string, Option> $options
     *
     * @return string
     */
    public function getOptionsJsonConfig(array $options): string
    {
        return $this->json->encode($this->getOptionsJsonConfigData($options)) ?? '{}';
    }

    /**
     * Returns the price configuration of the options by option ID.
     *
     * Options with values (drop-down, radio buttons, checkboxes, multiple select) get the configuration of each value
     * by value ID. Observers of the event "catalog_product_option_price_configuration_after" can change the
     * configuration; they also get the options.
     *
     * @param Option[] $options
     * @phpstan-param array<int|string, Option> $options
     *
     * @return array
     * @phpstan-return array<int|string, mixed>
     */
    public function getOptionsJsonConfigData(array $options): array
    {
        $config = [];

        foreach ($options as $option) {
            if ($option->hasValues()) {
                $priceValue = [];

                foreach ($option->getValues() ?? [] as $valueId => $value) {
                    if ($value instanceof Value) {
                        $priceValue[ $valueId ] = $this->getPriceConfiguration($value);
                    }
                }
            } else {
                $priceValue = $this->getPriceConfiguration($option);
            }

            $config[ $option->getId() ] = $priceValue;
        }

        $configObj = new DataObject(['config' => $config]);

        // other modules can change the configuration in the object, for example Magento_Weee
        $this->eventManager->dispatch(
            'catalog_product_option_price_configuration_after',
            ['configObj' => $configObj, 'options' => $options]
        );

        $config = $configObj->getData('config');

        return is_array($config) ? $config : [];
    }

    /**
     * Returns the prices, the price type and the title of an option or an option value.
     *
     * @param Value|Option $option
     *
     * @return array
     * @phpstan-return array{
     *     prices: array{
     *         oldPrice: array{amount: float, adjustments: array<string, float>},
     *         basePrice: array{amount: float},
     *         finalPrice: array{amount: float}
     *     },
     *     type: mixed,
     *     name: mixed
     * }
     */
    protected function getPriceConfiguration(Value|Option $option): array
    {
        $product = $option->getProduct();

        if (! $product instanceof Product) {
            throw new LogicException('The product option has no product');
        }

        $optionPrice = $this->convertPrice($option, $option->getPrice(true));
        $regularPrice = $this->convertPrice($option, $option->getRegularPrice());

        return [
            'prices' => [
                'oldPrice'   => [
                    'amount'      => $this->getTaxPrice($product, $regularPrice, true),
                    'adjustments' => []
                ],
                'basePrice'  => [
                    'amount' => $this->getTaxPrice($product, $optionPrice, false)
                ],
                'finalPrice' => [
                    'amount' => $this->getTaxPrice($product, $optionPrice, true)
                ]
            ],
            'type'   => $option->getPriceType(),
            'name'   => $option->getTitle()
        ];
    }

    /**
     * Returns a price of the option in the currency of the store.
     *
     * Fixed prices are stored in the base currency and converted. Percentage prices are calculated from the price of
     * the product, which already is in the currency of the store.
     *
     * @param Value|Option $option
     * @param mixed $price
     *
     * @return float
     */
    private function convertPrice(Value|Option $option, mixed $price): float
    {
        $price = $this->floatValue($price);

        return $option->getPriceType() === Value::TYPE_PERCENT ?
            $price :
            $this->floatValue($this->pricingHelper->currency($price, false, false));
    }

    /**
     * Returns the price with or without tax, not rounded, as Magento's block of product options does.
     *
     * @param Product $product
     * @param float $price
     * @param bool $includingTax
     *
     * @return float
     */
    private function getTaxPrice(Product $product, float $price, bool $includingTax): float
    {
        return $this->floatValue(
            $this->catalogHelper->getTaxPrice($product, $price, $includingTax, null, null, null, null, null, false)
        );
    }

    /**
     * Returns a price as float, Magento loads prices as strings.
     *
     * @param mixed $value
     *
     * @return float
     */
    private function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float)$value : 0.0;
    }
}
