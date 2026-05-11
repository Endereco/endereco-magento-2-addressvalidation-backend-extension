<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Parc\AddressValidation\Model;

use Magento\Framework\Model\AbstractModel;
use Parc\AddressValidation\Api\Data\AddressValidationInterface;

class AddressValidation extends AbstractModel implements AddressValidationInterface
{
    /**
     * @inheritDoc
     */
    public function _construct(): void
    {
        $this->_init(ResourceModel\AddressValidation::class);
    }

    /**
     * @inheritDoc
     */
    public function getAddressValidationId(): int
    {
        return (int)$this->getData(self::ADDRESS_VALIDATION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setAddressValidationId(int $addressValidationId): AddressValidationInterface
    {
        return $this->setData(self::ADDRESS_VALIDATION_ID, $addressValidationId);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): AddressValidationInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): string
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(string $orderId): AddressValidationInterface
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderIncrementId(): string
    {
        return $this->getData(self::ORDER_INCREMENT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderIncrementId(string $orderIncrementId): AddressValidationInterface
    {
        return $this->setData(self::ORDER_INCREMENT_ID, $orderIncrementId);
    }

    /**
     * @inheritDoc
     */
    public function getOrigZipCode(): ?string
    {
        return $this->getData(self::ORIG_ZIP_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setOrigZipCode(string $origZipCode): AddressValidationInterface
    {
        return $this->setData(self::ORIG_ZIP_CODE, $origZipCode);
    }

    /**
     * @inheritDoc
     */
    public function getOrigCity(): ?string
    {
        return $this->getData(self::ORIG_CITY);
    }

    /**
     * @inheritDoc
     */
    public function setOrigCity(string $origCity): AddressValidationInterface
    {
        return $this->setData(self::ORIG_CITY, $origCity);
    }

    /**
     * @inheritDoc
     */
    public function getOrigStreetFull(): ?string
    {
        return $this->getData(self::ORIG_STREET_FULL);
    }

    /**
     * @inheritDoc
     */
    public function setOrigStreetFull(string $origStreetFull): AddressValidationInterface
    {
        return $this->setData(self::ORIG_STREET_FULL, $origStreetFull);
    }

    /**
     * @inheritDoc
     */
    public function getApiZipCode(): ?string
    {
        return $this->getData(self::API_ZIP_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setApiZipCode(?string $apiZipCode): AddressValidationInterface
    {
        return $this->setData(self::API_ZIP_CODE, $apiZipCode);
    }

    /**
     * @inheritDoc
     */
    public function getApiCity(): ?string
    {
        return $this->getData(self::API_CITY);
    }

    /**
     * @inheritDoc
     */
    public function setApiCity(?string $apiCity): AddressValidationInterface
    {
        return $this->setData(self::API_CITY, $apiCity);
    }

    /**
     * @inheritDoc
     */
    public function getApiStreet(): ?string
    {
        return $this->getData(self::API_STREET);
    }

    /**
     * @inheritDoc
     */
    public function setApiStreet(?string $apiStreet): AddressValidationInterface
    {
        return $this->setData(self::API_STREET, $apiStreet);
    }

    /**
     * @inheritDoc
     */
    public function getApiHouseNumber(): ?string
    {
        return $this->getData(self::API_HOUSE_NUMBER);
    }

    /**
     * @inheritDoc
     */
    public function setApiHouseNumber(?string $apiHouseNumber): AddressValidationInterface
    {
        return $this->setData(self::API_HOUSE_NUMBER, $apiHouseNumber);
    }

    /**
     * @inheritDoc
     */
    public function getApiAdditionalInformation(): ?string
    {
        return $this->getData(self::API_ADDITIONAL_INFORMATION);
    }

    /**
     * @inheritDoc
     */
    public function setApiAdditionalInformation(?string $apiAdditionalInformation): AddressValidationInterface
    {
        return $this->setData(self::API_ADDITIONAL_INFORMATION, $apiAdditionalInformation);
    }

    /**
     * @inheritDoc
     */
    public function getStatusCodes(): ?string
    {
        return $this->getData(self::STATUS_CODES);
    }

    /**
     * @inheritDoc
     */
    public function setStatusCodes(?string $statusCodes): AddressValidationInterface
    {
        return $this->setData(self::STATUS_CODES, $statusCodes);
    }

    /**
     * @inheritDoc
     */
    public function getManuZipCode(): ?string
    {
        return $this->getData(self::MANU_ZIP_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setManuZipCode(?string $manuZipCode): AddressValidationInterface
    {
        return $this->setData(self::MANU_ZIP_CODE, $manuZipCode);
    }

    /**
     * @inheritDoc
     */
    public function getManuCity(): ?string
    {
        return $this->getData(self::MANU_CITY);
    }

    /**
     * @inheritDoc
     */
    public function setManuCity(?string $manuCity): AddressValidationInterface
    {
        return $this->setData(self::MANU_CITY, $manuCity);
    }

    /**
     * @inheritDoc
     */
    public function getManuStreet(): ?string
    {
        return $this->getData(self::MANU_STREET);
    }

    /**
     * @inheritDoc
     */
    public function setManuStreet(?string $manuStreet): AddressValidationInterface
    {
        return $this->setData(self::MANU_STREET, $manuStreet);
    }

    /**
     * @inheritDoc
     */
    public function getManuHouseNumber(): ?string
    {
        return $this->getData(self::MANU_HOUSE_NUMBER);
    }

    /**
     * @inheritDoc
     */
    public function setManuHouseNumber(?string $manuHouseNumber): AddressValidationInterface
    {
        return $this->setData(self::MANU_HOUSE_NUMBER, $manuHouseNumber);
    }

    /**
     * @inheritDoc
     */
    public function getManuAdditionalInformation(): ?string
    {
        return $this->getData(self::MANU_ADDITIONAL_INFORMATION);
    }

    /**
     * @inheritDoc
     */
    public function setManuAdditionalInformation(?string $manuAdditionalInformation): AddressValidationInterface
    {
        return $this->setData(self::MANU_ADDITIONAL_INFORMATION, $manuAdditionalInformation);
    }

    /**
     * @inheritDoc
     */
    public function getEditedBy(): ?string
    {
        return $this->getData(self::EDITED_BY);
    }

    /**
     * @inheritDoc
     */
    public function setEditedBy(string $editedBy): AddressValidationInterface
    {
        return $this->setData(self::EDITED_BY, $editedBy);
    }

    /**
     * @inheritDoc
     */
    public function getEditedAt(): ?string
    {
        return $this->getData(self::EDITED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setEditedAt(string $editedAt): AddressValidationInterface
    {
        return $this->setData(self::EDITED_AT, $editedAt);
    }
}
