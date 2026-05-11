<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Parc\AddressValidation\Api\Data;

interface AddressValidationInterface
{
    public const ADDRESS_VALIDATION_ID       = 'address_validation_id';
    public const CREATED_AT                  = 'created_at';
    public const ORDER_ID                    = 'order_id';
    public const ORDER_INCREMENT_ID          = 'order_increment_id';
    public const ORIG_ZIP_CODE               = 'orig_zip_code';
    public const ORIG_CITY                   = 'orig_city';
    public const ORIG_STREET_FULL            = 'orig_street_full';
    public const API_ZIP_CODE                = 'api_zip_code';
    public const API_CITY                    = 'api_city';
    public const API_STREET                  = 'api_street';
    public const API_HOUSE_NUMBER            = 'api_house_number';
    public const API_ADDITIONAL_INFORMATION  = 'api_additional_information';
    public const STATUS_CODES                = 'status_codes';
    public const MANU_ZIP_CODE               = 'manu_zip_code';
    public const MANU_CITY                   = 'manu_city';
    public const MANU_STREET                 = 'manu_street';
    public const MANU_HOUSE_NUMBER           = 'manu_house_number';
    public const MANU_ADDITIONAL_INFORMATION = 'manu_additional_information';
    public const EDITED_BY                   = 'edited_by';
    public const EDITED_AT                   = 'edited_at';

    /**
     * Get address_validation_id
     * @return int
     */
    public function getAddressValidationId(): int;

    /**
     * Set address_validation_id
     *
     * @param int $addressValidationId
     *
     * @return AddressValidationInterface
     */
    public function setAddressValidationId(int $addressValidationId): AddressValidationInterface;

    /**
     * Get created_at
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Get created_at
     *
     * @param string $createdAt
     *
     * @return AddressValidationInterface
     */
    public function setCreatedAt(string $createdAt): AddressValidationInterface;

    /**
     * Get order_id
     * @return string
     */
    public function getOrderId(): string;

    /**
     * Set order_id
     *
     * @param string $orderId
     *
     * @return AddressValidationInterface
     */
    public function setOrderId(string $orderId): AddressValidationInterface;

    /**
     * Get order_increment_id
     * @return string
     */
    public function getOrderIncrementId(): string;

    /**
     * Set order_increment_id
     *
     * @param string $orderIncrementId
     *
     * @return AddressValidationInterface
     */
    public function setOrderIncrementId(string $orderIncrementId): AddressValidationInterface;

    /**
     * Get orig_zip_code
     * @return string|null
     */
    public function getOrigZipCode(): ?string;

    /**
     * Set orig_zip_code
     *
     * @param string $origZipCode
     *
     * @return AddressValidationInterface
     */
    public function setOrigZipCode(string $origZipCode): AddressValidationInterface;

    /**
     * Get orig_city
     * @return string|null
     */
    public function getOrigCity(): ?string;

    /**
     * Set orig_city
     *
     * @param string $origCity
     *
     * @return AddressValidationInterface
     */
    public function setOrigCity(string $origCity): AddressValidationInterface;

    /**
     * Get orig_street_full
     * @return string|null
     */
    public function getOrigStreetFull(): ?string;

    /**
     * Set orig_street_full
     *
     * @param string $origStreetFull
     *
     * @return AddressValidationInterface
     */
    public function setOrigStreetFull(string $origStreetFull): AddressValidationInterface;

    /**
     * Get api_zip_code
     * @return string|null
     */
    public function getApiZipCode(): ?string;

    /**
     * Set api_zip_code
     *
     * @param string|null $apiZipCode
     *
     * @return AddressValidationInterface
     */
    public function setApiZipCode(?string $apiZipCode): AddressValidationInterface;

    /**
     * Get api_city
     * @return string|null
     */
    public function getApiCity(): ?string;

    /**
     * Set api_city
     *
     * @param string|null $apiCity
     *
     * @return AddressValidationInterface
     */
    public function setApiCity(?string $apiCity): AddressValidationInterface;

    /**
     * Get api_street
     * @return string|null
     */
    public function getApiStreet(): ?string;

    /**
     * Set api_street
     *
     * @param string|null $apiStreet
     *
     * @return AddressValidationInterface
     */
    public function setApiStreet(?string $apiStreet): AddressValidationInterface;

    /**
     * Get api_house_number
     * @return string|null
     */
    public function getApiHouseNumber(): ?string;

    /**
     * Set api_house_number
     *
     * @param string|null $apiHouseNumber
     *
     * @return AddressValidationInterface
     */
    public function setApiHouseNumber(?string $apiHouseNumber): AddressValidationInterface;

    /**
     * Get api_additional_information
     * @return string|null
     */
    public function getApiAdditionalInformation(): ?string;

    /**
     * Set api_additional_information
     *
     * @param string|null $apiAdditionalInformation
     *
     * @return AddressValidationInterface
     */
    public function setApiAdditionalInformation(?string $apiAdditionalInformation): AddressValidationInterface;

    /**
     * Get status_codes
     * @return string|null
     */
    public function getStatusCodes(): ?string;

    /**
     * Set status_codes
     *
     * @param string|null $statusCodes
     *
     * @return AddressValidationInterface
     */
    public function setStatusCodes(?string $statusCodes): AddressValidationInterface;

    /**
     * Get manu_zip_code
     * @return string|null
     */
    public function getManuZipCode(): ?string;

    /**
     * Set manu_zip_code
     *
     * @param string|null $manuZipCode
     *
     * @return AddressValidationInterface
     */
    public function setManuZipCode(?string $manuZipCode): AddressValidationInterface;

    /**
     * Get manu_city
     * @return string|null
     */
    public function getManuCity(): ?string;

    /**
     * Set manu_city
     *
     * @param string|null $manuCity
     *
     * @return AddressValidationInterface
     */
    public function setManuCity(?string $manuCity): AddressValidationInterface;

    /**
     * Get manu_street
     * @return string|null
     */
    public function getManuStreet(): ?string;

    /**
     * Set manu_street
     *
     * @param string|null $manuStreet
     *
     * @return AddressValidationInterface
     */
    public function setManuStreet(?string $manuStreet): AddressValidationInterface;

    /**
     * Get manu_house_number
     * @return string|null
     */
    public function getManuHouseNumber(): ?string;

    /**
     * Set manu_house_number
     *
     * @param string|null $manuHouseNumber
     *
     * @return AddressValidationInterface
     */
    public function setManuHouseNumber(?string $manuHouseNumber): AddressValidationInterface;

    /**
     * Get manu_additional_information
     * @return string|null
     */
    public function getManuAdditionalInformation(): ?string;

    /**
     * Set manu_additional_information
     *
     * @param string|null $manuAdditionalInformation
     *
     * @return AddressValidationInterface
     */
    public function setManuAdditionalInformation(?string $manuAdditionalInformation): AddressValidationInterface;

    /**
     * Get edited_by
     * @return string|null
     */
    public function getEditedBy(): ?string;

    /**
     * Set edited_by
     *
     * @param string $editedBy
     *
     * @return AddressValidationInterface
     */
    public function setEditedBy(string $editedBy): AddressValidationInterface;

    /**
     * Get edited_at
     * @return string|null
     */
    public function getEditedAt(): ?string;

    /**
     * Set edited_at
     *
     * @param string $editedAt
     *
     * @return AddressValidationInterface
     */
    public function setEditedAt(string $editedAt): AddressValidationInterface;
}
