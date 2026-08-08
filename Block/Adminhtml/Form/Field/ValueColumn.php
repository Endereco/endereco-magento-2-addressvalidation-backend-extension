<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Block\Adminhtml\Form\Field;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ValueColumn extends Select
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    public function __construct(
        ResourceConnection $resourceConnection,
        Context            $context,
        ScopeConfigInterface $scopeConfig,
        array              $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Set "name" for <select> element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }

        $this->setExtraParams('style="min-width:350px"');

        return parent::_toHtml();
    }

    private function getSourceOptions(): array
    {
        $options = [
            [
                'value' => 'val_zip_code',
                'label' => 'validated zip code'
            ],
            [
                'value' => 'val_city',
                'label' => 'validated city'
            ],
            [
                'value' => 'val_street',
                'label' => 'validated street'
            ],
            [
                'value' => 'val_house_number',
                'label' => 'validated house number'
            ],
            [
                'value' => 'val_additional_information',
                'label' => 'validated additional info'
            ],
            [
                'value' => 'val_subdivision_code',
                'label' => 'validated region (ISO 3166-2)'
            ]
        ];

        $relevantTables = $this->scopeConfig->getValue('parc_addressvalidation/csvmapping/relevanttables');

        if ($relevantTables) {
            $tablesArray = explode(',', $relevantTables);
            foreach ($tablesArray as $table) {
                $tableName  = explode('.', $table)[0];
                $connection = $this->resourceConnection->getConnection();
                $columns    = $connection->describeTable($tableName);

                foreach ($columns as $column => $details) {
                    $options[] = [
                        'label' => $tableName . '.' . $column,
                        'value' => $tableName . '.' . $column
                    ];
                }
            }
        }

        return $options;
    }
}
