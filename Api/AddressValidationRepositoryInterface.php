<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Parc\AddressValidation\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Parc\AddressValidation\Api\Data\AddressValidationInterface;
use Parc\AddressValidation\Api\Data\AddressValidationSearchResultsInterface;

interface AddressValidationRepositoryInterface
{
    /**
     * Save address_validation
     *
     * @param AddressValidationInterface $addressValidation
     *
     * @return AddressValidationInterface
     * @throws LocalizedException
     */
    public function save(
        AddressValidationInterface $addressValidation
    ): AddressValidationInterface;

    /**
     * Retrieve address_validation
     *
     * @param int $addressValidationId
     *
     * @return AddressValidationInterface
     * @throws LocalizedException
     */
    public function get(int $addressValidationId): AddressValidationInterface;

    /**
     * Retrieve address_validation matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return AddressValidationSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    ): AddressValidationSearchResultsInterface;

    /**
     * Delete address_validation
     *
     * @param AddressValidationInterface $addressValidation
     *
     * @return bool true on success
     * @throws LocalizedException
     */
    public function delete(
        AddressValidationInterface $addressValidation
    ): bool;

    /**
     * Delete address_validation by ID
     *
     * @param int $addressValidationId
     *
     * @return bool true on success
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById(int $addressValidationId): bool;
}
