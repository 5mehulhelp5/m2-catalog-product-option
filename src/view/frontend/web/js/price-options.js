/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

define([
    'jquery',
    'underscore',
    'priceUtils',
    'Magento_Catalog/js/price-options'
], function ($, _, utils) {
    'use strict';

    $.widget('infrangible.priceOptions', $.mage.priceOptions, {
        _create: function() {
            var form = this.element,
                options = $(this.options.optionsSelector, form);

            $.each(options, function() {
                var role = $(this).attr('data-role');

                if (! role) {
                    $(this).attr('data-role', 'default');
                }
            });

            this.options.optionHandlers.default = this.getDefaultHandler();

            this._super();
        },

        _onOptionChanged: function(event) {
            this._super(event);

            var option = $(event.target);
            var optionId = utils.findOptionId(option[0]);

            option.trigger('product_option_changed', [optionId]);
        },

        getDefaultHandler: function getQtyHandler() {
            var self = this;

            return function (element, optionConfig) {
                return self.getOptionValue(element, optionConfig);
            };
        },

        getOptionValue: function defaultGetOptionValue(element, optionsConfig) {
            var changes = {},
                optionId = utils.findOptionId(element[0]),
                optionName = element.prop('name'),
                optionType = element.prop('type'),
                optionValue = element.val(),
                optionConfig = optionsConfig[optionId],
                optionHash = optionName;

            switch (optionType) {
                case 'text':
                case 'textarea':
                    changes[optionHash] = optionValue ? optionConfig.prices : {};
                    break;

                case 'radio':
                    if (element.is(':checked')) {
                        changes[optionHash] = optionConfig[optionValue] && optionConfig[optionValue].prices || {};
                    }
                    break;

                case 'select-one':
                    changes[optionHash] = optionConfig[optionValue] && optionConfig[optionValue].prices || {};
                    break;

                case 'select-multiple':
                    _.each(optionConfig, function (row, optionValueCode) {
                        optionHash = optionName + '##' + optionValueCode;
                        changes[optionHash] = _.contains(optionValue, optionValueCode) ? row.prices : {};
                    });
                    break;

                case 'checkbox':
                    optionHash = optionName + '##' + optionValue;
                    changes[optionHash] = element.is(':checked') ? optionConfig[optionValue].prices : {};
                    break;

                case 'file':
                    // Checking for 'disable' property equal to checking DOMNode with id*="change-"
                    changes[optionHash] = optionValue || element.prop('disabled') ? optionConfig.prices : {};
                    break;

                default:
                    console.error('Unsupported option type: "' + optionType + '"');
                    break;
            }

            element.trigger('product_option_changes', [optionId, optionHash, element, optionsConfig, changes]);

            return changes;
        }
    });

    return $.infrangible.priceOptions;
});
