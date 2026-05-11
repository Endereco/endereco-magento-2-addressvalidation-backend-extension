<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Parc\AddressValidation\Model\ResourceModel\AddressValidation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Parc\AddressValidation\Model\ResourceModel\AddressValidation;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'address_validation_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(
            \Parc\AddressValidation\Model\AddressValidation::class,
            AddressValidation::class
        );
    }
}
