<?php

/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Parc\AddressValidation\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface AddressValidationSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get address_validation list.
     *
     * @return AddressValidationInterface[]
     */
    public function getItems(): array;

    /**
     * Set country list.
     *
     * @param AddressValidationInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items): static;
}
