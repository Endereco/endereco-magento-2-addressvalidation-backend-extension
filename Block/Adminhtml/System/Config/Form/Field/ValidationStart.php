<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Block\Adminhtml\System\Config\Form\Field;

use IntlDateFormatter;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Read-only display of the validation start (set by the "Enable" backend model).
 *
 * Renders plain text instead of an input on purpose: the value must never be
 * posted back with the form, otherwise saving the section would overwrite the
 * stored UTC timestamp with the localised display string.
 */
class ValidationStart extends Field
{
    protected function _getElementHtml(AbstractElement $element) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $value = (string)$element->getValue();

        if ($value === '') {
            return '<span>' . $this->escapeHtml((string)__('Not set yet')) . '</span>';
        }

        try {
            // Stored in UTC; shown in the shop's configured timezone.
            $date = new \DateTime($value, new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            return '<span>' . $this->escapeHtml($value) . '</span>';
        }

        $formatted = $this->_localeDate->formatDateTime(
            $date,
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::MEDIUM
        );

        return '<span>' . $this->escapeHtml(
            sprintf('%s (%s)', $formatted, $this->_localeDate->getConfigTimezone())
        ) . '</span>';
    }

    /**
     * No "Use system value" checkbox and no scope switch for a display-only field.
     */
    protected function _isInheritCheckboxRequired(AbstractElement $element) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return false;
    }
}
