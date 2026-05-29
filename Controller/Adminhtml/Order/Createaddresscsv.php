<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\File\Csv;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Zend_Db_Expr;

class Createaddresscsv extends Action
{
    protected Filter $filter;

    protected OrderCollectionFactory $orderCollectionFactory;

    protected FileFactory $fileFactory;

    protected Csv $csvProcessor;

    protected DirectoryList $directoryList;

    protected ScopeConfigInterface $scopeConfig;

    protected ResourceConnection $resourceConnection;

    protected string $fileName;

    protected string $filePath;

    protected array $tableKeys;

    /**
     * @param Context                $context
     * @param Filter                 $filter
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param FileFactory            $fileFactory
     * @param Csv                    $csvProcessor
     * @param DirectoryList          $directoryList
     * @param ScopeConfigInterface   $scopeConfig
     * @param ResourceConnection     $resourceConnection
     *
     * @throws FileSystemException
     */
    public function __construct(
        Context                $context,
        Filter                 $filter,
        OrderCollectionFactory $orderCollectionFactory,
        FileFactory            $fileFactory,
        Csv                    $csvProcessor,
        DirectoryList          $directoryList,
        ScopeConfigInterface   $scopeConfig,
        ResourceConnection     $resourceConnection
    ) {
        parent::__construct($context);
        $this->filter                 = $filter;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->fileFactory            = $fileFactory;
        $this->csvProcessor           = $csvProcessor;
        $this->directoryList          = $directoryList;
        $this->scopeConfig            = $scopeConfig;
        $this->resourceConnection     = $resourceConnection;

        $this->tableKeys = [];
    }

    public function execute()
    {
        $this->fileName = sprintf(
            'validated_addresses_%s_%s.csv',
            date('Ymd_His'),
            substr(bin2hex(random_bytes(2)), 0, 4)
        );
        $this->filePath = $this->directoryList->getPath(DirectoryList::TMP) . '/' . $this->fileName;

        $collection = $this->filter->getCollection($this->orderCollectionFactory->create());

        $tables  = $this->scopeConfig->getValue('parc_addressvalidation/csvmapping/relevanttables');
        $mapping = $this->scopeConfig->getValue('parc_addressvalidation/csvmapping/mapping');

        if (!$mapping || !$tables) {
            $this->messageManager->addErrorMessage(__('You must first define the mapping in the store config'));

            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        $tables = explode(',', $tables);
        foreach ($tables as $table) {
            $tableArray                      = explode('.', $table);
            $this->tableKeys[$tableArray[0]] = $tableArray[1];
        }

        $mapping = json_decode($mapping, true);
        $headers = array_column($mapping, 'header');
        $csvData = [$headers];

        foreach ($collection as $order) {
            $orderData = [];
            $orderId   = $order->getId();

            foreach ($mapping as $column) {
                $columnValue = $column['value'];
                $orderData[] = str_contains($columnValue, '.')
                    ? $this->getDirectDBValue($columnValue, $orderId)
                    : $this->getValidatedDBValue($columnValue, $orderId);
            }

            $csvData[] = $orderData;
        }

        $this->csvProcessor
            ->setEnclosure('"')
            ->setDelimiter(';')
            ->saveData($this->filePath, $csvData);

        return $this->fileFactory->create($this->fileName, [
            'type'  => 'filename',
            'value' => $this->fileName,
            'rm'    => true
        ], DirectoryList::TMP);
    }

    private function getDirectDBValue($columnValue, $orderId): string
    {
        $columnArray = explode('.', $columnValue);
        $tableName   = $columnArray[0];
        $columnName  = $columnArray[1];
        $key         = $this->tableKeys[$tableName];

        $connection = $this->resourceConnection->getConnection();
        $query      = $connection->select()
                                 ->from($tableName, [$columnName])
                                 ->where("$key = ?", $orderId)
                                 ->limit(1);

        return (string)$connection->fetchOne($query);
    }

    private function getValidatedDBValue($columnValue, $orderId): string
    {
        $baseColumnName = substr($columnValue, 4);
        $tableName      = 'parc_addressvalidation';

        $connection = $this->resourceConnection->getConnection();
        $query      = $connection->select()
                                 ->from($tableName, [
                                     $baseColumnName => new \Zend_Db_Expr(
                                         "CASE
                                         WHEN edited_by IS NOT NULL AND edited_by <> '' THEN manu_$baseColumnName
                                         ELSE api_$baseColumnName
                                         END"
                                     )
                                 ])
                                 ->where('order_id = ?', $orderId)
                                 ->limit(1);

        return (string)$connection->fetchOne($query);
    }
}
