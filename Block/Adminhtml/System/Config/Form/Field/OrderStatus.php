<?php

namespace Parc\AddressValidation\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class OrderStatus extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = parent::_getElementHtml($element);

        // Add a JS snippet after the multiselect
        $html .= <<<HTML
<script>
require(['jquery'], function($) {
    $(document).ready(function () {
        function updateOrderStatusOptions() {
            var selectedValidationStatus = $('#row_parc_addressvalidation_general_validationstatus select').val();
            var \$orderStatusSelect = $('#row_parc_addressvalidation_general_orderstatus select');

            \$orderStatusSelect.find('option').each(function () {
                var value = $(this).val();
                if (value === selectedValidationStatus) {
                    $(this).prop('disabled', true).css('color', '#ccc');
                } else {
                    $(this).prop('disabled', false).css('color', '');
                }
            });
        }

        // Initial run
        updateOrderStatusOptions();

        // Update on change
        $('#row_parc_addressvalidation_general_validationstatus select').on('change', updateOrderStatusOptions);
    });
});
</script>
HTML;

        return $html;
    }
}
