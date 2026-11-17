# Changelog

## 2.0.0

### Breaking changes

- Requires Magento 2.4.6 or newer and PHP 8.2 or newer. Magento 2.2 to 2.4.5 and PHP 7.1 to 8.1 are no longer
  supported; use version 1.x for these.
- Requires `infrangible/m2-foundation` 3.0, `fewedev/base` 2.4 and `magento/module-catalog`.
- All parameters and return values of the helper have native types, and its constructor uses constructor property
  promotion. `getPriceConfiguration()` throws a `LogicException` if the option has no product.

### Fixed

- The old price of options and option values is calculated like the final price: fixed prices are converted into the
  currency of the store, and the tax is included. Before, the tax was missing (Magento 2.4.7 and newer include the tax,
  but no longer convert the currency).

### Changed

- The module is loaded after Magento_Catalog, whose price script it extends.
- Dependencies only allow patch releases of the tested minor versions (`~X.Y.Z`).
- Checks with PHPStan level 9, the Magento coding standard and unit tests on every push.
