# Infrangible Catalog Product Option

Shared base of the Infrangible modules for custom product options. It extends the price script of custom options on
the product page and builds the price configuration of options for other lists of options, so that other modules can
change both.

## Price script

The module replaces Magento's price script of custom options (`priceOptions`) with the widget
`Infrangible_CatalogProductOption/js/price-options`, which extends Magento's widget:

- Options without their own handler get a default handler that calculates the price changes like Magento does for all
  standard option types (text field, text area, drop-down, multiple select, radio buttons, checkboxes, file).
- When a shopper changes an option, the option triggers the jQuery event `product_option_changed` with the option ID.
- The default handler triggers the jQuery event `product_option_changes` with the option ID, the option hash, the
  option element, the price configuration of all options and the calculated price changes.

Other modules extend the widget with a RequireJS mixin of `Infrangible_CatalogProductOption/js/price-options`, for
example to add handlers for their own option types.

## Price configuration

`Infrangible\CatalogProductOption\Helper\Data` builds the price configuration that the price script needs for a list
of options, like Magento's block of product options does for the options of the product page:

- `getOptionsJsonConfig(array $options): string` returns it as JSON for the template.
- `getOptionsJsonConfigData(array $options): array` returns it as array, by option ID and, for options with values,
  by value ID. Each entry contains the old price, the base price (without tax) and the final price (with tax) in the
  currency of the store, the price type and the title.

Before it returns the configuration, the helper dispatches the event `catalog_product_option_price_configuration_after`
with the configuration in `configObj` (a `DataObject` with the key `config`) and the options in `options`. Observers
can change the configuration in `configObj`.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.6 or newer
- PHP 8.2 or newer

## Installation

```bash
composer require infrangible/m2-catalog-product-option
bin/magento module:enable Infrangible_CatalogProductOption
bin/magento setup:upgrade
```

In production mode, also run `bin/magento setup:di:compile` and `bin/magento setup:static-content:deploy`.

## License

Infrangible Catalog Product Option is licensed under the MIT License - see the LICENSE file for details.
