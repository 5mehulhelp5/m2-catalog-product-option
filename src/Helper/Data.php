<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOption\Helper;

use FeWeDev\Base\Json;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Option\Value;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Data
{
    /** @var ManagerInterface */
    protected $eventManager;

    /** @var Json */
    protected $json;

    /** @var \Magento\Framework\Pricing\Helper\Data */
    protected $pricingHelper;

    /** @var \Magento\Catalog\Helper\Data */
    protected $catalogHelper;

    public function __construct(
        \Magento\Catalog\Helper\Data $catalogHelper,
        ManagerInterface $eventManager,
        Json $json,
        \Magento\Framework\Pricing\Helper\Data $pricingHelper
    ) {
        $this->catalogHelper = $catalogHelper;
        $this->eventManager = $eventManager;
        $this->json = $json;
        $this->pricingHelper = $pricingHelper;
    }

    /**
     * @param Option[] $options
     */
    public function getOptionsJsonConfig(array $options): string
    {
        return $this->json->encode($this->getOptionsJsonConfigData($options));
    }

    /**
     * @param Option[] $options
     */
    public function getOptionsJsonConfigData(array $options): array
    {
        $config = [];

        foreach ($options as $option) {
            if ($option->hasValues()) {
                $tmpPriceValues = [];

                /** @var Value $value */
                foreach ($option->getValues() as $valueId => $value) {
                    $tmpPriceValues[ $valueId ] = $this->getPriceConfiguration($value);
                }

                $priceValue = $tmpPriceValues;
            } else {
                $priceValue = $this->getPriceConfiguration($option);
            }

            $config[ $option->getId() ] = $priceValue;
        }

        $configObj = new DataObject(
            [
                'config' => $config,
            ]
        );

        //pass the return array encapsulated in an object for the other modules to be able to alter it eg: weee
        $this->eventManager->dispatch(
            'catalog_product_option_price_configuration_after',
            ['configObj' => $configObj]
        );

        return $configObj->getData('config');
    }

    /**
     * @param Value|Option $option
     */
    protected function getPriceConfiguration($option): array
    {
        $optionPrice = $option->getPrice(true);

        if ($option->getPriceType() !== Value::TYPE_PERCENT) {
            $optionPrice = $this->pricingHelper->currency(
                $optionPrice,
                false,
                false
            );
        }

        return [
            'prices' => [
                'oldPrice'   => [
                    'amount'      => $this->pricingHelper->currency(
                        $option->getRegularPrice(),
                        false,
                        false
                    ),
                    'adjustments' => [],
                ],
                'basePrice'  => [
                    'amount' => $this->catalogHelper->getTaxPrice(
                        $option->getProduct(),
                        $optionPrice,
                        false,
                        null,
                        null,
                        null,
                        null,
                        null,
                        false
                    ),
                ],
                'finalPrice' => [
                    'amount' => $this->catalogHelper->getTaxPrice(
                        $option->getProduct(),
                        $optionPrice,
                        true,
                        null,
                        null,
                        null,
                        null,
                        null,
                        false
                    ),
                ]
            ],
            'type'   => $option->getPriceType(),
            'name'   => $option->getTitle(),
        ];
    }
}
