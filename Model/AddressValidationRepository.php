<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Parc\AddressValidation\Model;

use Exception;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Parc\AddressValidation\Api\Data\AddressValidationInterface;
use Parc\AddressValidation\Api\Data\AddressValidationInterfaceFactory;
use Parc\AddressValidation\Api\Data\AddressValidationSearchResultsInterface;
use Parc\AddressValidation\Api\Data\AddressValidationSearchResultsInterfaceFactory;
use Parc\AddressValidation\Api\AddressValidationRepositoryInterface;
use Parc\AddressValidation\Model\ResourceModel\AddressValidation as ResourceAddressValidation;
use Parc\AddressValidation\Model\ResourceModel\AddressValidation\CollectionFactory
    as AddressValidationCollectionFactory;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class AddressValidationRepository implements AddressValidationRepositoryInterface
{
    protected string $overwriteOriginal;

    /**
     * @var AddressValidationCollectionFactory
     */
    protected AddressValidationCollectionFactory $addressValidationCollectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected CollectionProcessorInterface $collectionProcessor;

    /**
     * @var AddressValidationInterfaceFactory
     */
    protected AddressValidationInterfaceFactory $addressValidationFactory;

    /**
     * @var AddressValidationSearchResultsInterfaceFactory
     */
    protected AddressValidationSearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var ResourceAddressValidation
     */
    protected ResourceAddressValidation $resource;

    /**
     * @var CountryFactory
     */
    protected CountryFactory $countryFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @param ResourceAddressValidation                      $resource
     * @param AddressValidationInterfaceFactory              $addressValidationFactory
     * @param AddressValidationCollectionFactory             $addressValidationCollectionFactory
     * @param AddressValidationSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface                   $collectionProcessor
     * @param CountryFactory                                 $countryFactory
     * @param ScopeConfigInterface                           $scopeConfig
     * @param OrderRepositoryInterface                       $orderRepository
     */
    public function __construct(
        ResourceAddressValidation                      $resource,
        AddressValidationInterfaceFactory              $addressValidationFactory,
        AddressValidationCollectionFactory             $addressValidationCollectionFactory,
        AddressValidationSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface                   $collectionProcessor,
        CountryFactory                                 $countryFactory,
        ScopeConfigInterface                           $scopeConfig,
        OrderRepositoryInterface                       $orderRepository
    ) {
        $this->resource                           = $resource;
        $this->addressValidationFactory           = $addressValidationFactory;
        $this->addressValidationCollectionFactory = $addressValidationCollectionFactory;
        $this->searchResultsFactory               = $searchResultsFactory;
        $this->collectionProcessor                = $collectionProcessor;
        $this->countryFactory                     = $countryFactory;
        $this->scopeConfig                        = $scopeConfig;
        $this->orderRepository                    = $orderRepository;

        $this->overwriteOriginal = (string)$this->scopeConfig->getValue('parc_addressvalidation/general/overwriteoriginal');
    }

    /**
     * @inheritDoc
     */
    public function save(
        AddressValidationInterface $addressValidation
    ): AddressValidationInterface {
        try {
            $this->resource->save($addressValidation);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the addressValidation: %1',
                $exception->getMessage()
            ));
        }

        return $addressValidation;
    }

    /**
     * @inheritDoc
     */
    public function get(int $addressValidationId): AddressValidationInterface
    {
        $addressValidation = $this->addressValidationFactory->create();
        $this->resource->load($addressValidation, $addressValidationId);
        if (!$addressValidation->getId()) {
            throw new NoSuchEntityException(__(
                'address_validation with id "%1" does not exist.',
                $addressValidationId
            ));
        }

        return $addressValidation;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    ): AddressValidationSearchResultsInterface {
        $collection = $this->addressValidationCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $items = [];
        foreach ($collection as $model) {
            $items[] = $model;
        }

        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(
        AddressValidationInterface $addressValidation
    ): bool {
        try {
            $addressValidationModel = $this->addressValidationFactory->create();
            $this->resource->load($addressValidationModel, $addressValidation->getAddressValidationId());
            $this->resource->delete($addressValidationModel);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the address_validation: %1',
                $exception->getMessage()
            ));
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $addressValidationId): bool
    {
        return $this->delete($this->get($addressValidationId));
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(int $orderId): AddressValidationInterface
    {
        $addressValidation = $this->getByOrderIdOrNull($orderId);
        if ($addressValidation === null) {
            throw new NoSuchEntityException(__(
                'No address_validation record exists for order id "%1".',
                $orderId
            ));
        }

        return $addressValidation;
    }

    /**
     * @inheritDoc
     */
    public function getByOrderIdOrNull(int $orderId): ?AddressValidationInterface
    {
        $addressValidation = $this->addressValidationFactory->create();
        $this->resource->load($addressValidation, $orderId, 'order_id');

        return $addressValidation->getId() ? $addressValidation : null;
    }

    /**
     * @throws LocalizedException
     */
    public function saveNewValues($values): void
    {
        $addressValidation = $this->get((int)$values['addressValidationId']);

        $fields = [
            'zipCode'        => [
                'setterMethod' => 'setManuZipCode',
                'apiKey'       => 'api_zip_code',
                'paramKey'     => 'zipCode'
            ],
            'city'           => [
                'setterMethod' => 'setManuCity',
                'apiKey'       => 'api_city',
                'paramKey'     => 'city'
            ],
            'street'         => [
                'setterMethod' => 'setManuStreet',
                'apiKey'       => 'api_street',
                'paramKey'     => 'street'
            ],
            'houseNumber'    => [
                'setterMethod' => 'setManuHouseNumber',
                'apiKey'       => 'api_house_number',
                'paramKey'     => 'houseNumber'
            ],
            'additionalInfo' => [
                'setterMethod' => 'setManuAdditionalInformation',
                'apiKey'       => 'api_additional_information',
                'paramKey'     => 'additionalInfo'
            ]
        ];

        foreach ($fields as $field) {
            if ($addressValidation[$field['apiKey']] !== $values[$field['paramKey']]) {
                $addressValidation->{$field['setterMethod']}($values[$field['paramKey']]);
            }
        }

        $addressValidation
            ->setEditedBy($values['edited_by'])
            ->setEditedAt(date('Y-m-d H:i:s'));

        $this->save($addressValidation);

        // if config is set to overwrite orig. shipping address this needs to be done here
        if ($this->overwriteOriginal) {
            $order           = $this->orderRepository->get($addressValidation->getOrderId());
            $shippingAddress = $order->getShippingAddress();
            // set validated address as orig. shipping address if configuration is enabled
            $street      = $addressValidation->getManuStreet() ?? $addressValidation->getApiStreet();
            $houseNumber = $addressValidation->getManuHouseNumber() ?? $addressValidation->getApiHouseNumber();
            $shippingAddress
                ->setPostcode($addressValidation->getManuZipCode() ?? $addressValidation->getApiZipCode())
                ->setCity($addressValidation->getManuCity() ?? $addressValidation->getApiCity())
                ->setStreet($street . ' ' . $houseNumber);

            $order->addCommentToStatusHistory(
                'the original delivery address was updated by the system to the address verified by ' .
                $values['edited_by'] .
                ' due to the config'
            );

            $this->orderRepository->save($order);
        }
    }
}
