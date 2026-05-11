<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Relevanttables implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            [
                'label' => 'sales_order_grid',
                'value' => 'sales_order_grid.entity_id'
            ],
            [
                'label' => 'sales_order_address',
                'value' => 'sales_order_address.parent_id'
            ],
            [
                'label' => 'parc_addressvalidation',
                'value' => 'parc_addressvalidation.order_id'
            ]
        ];
    }
}
