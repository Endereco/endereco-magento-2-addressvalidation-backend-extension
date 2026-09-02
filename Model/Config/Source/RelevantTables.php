<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class RelevantTables implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'label' => __('sales_order_grid'),
                'value' => 'sales_order_grid.entity_id'
            ],
            [
                'label' => __('sales_order_address'),
                'value' => 'sales_order_address.parent_id'
            ],
            [
                'label' => __('parc_addressvalidation'),
                'value' => 'parc_addressvalidation.order_id'
            ]
        ];
    }
}
