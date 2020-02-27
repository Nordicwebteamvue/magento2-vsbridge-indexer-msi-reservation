<?php /** @noinspection PhpUnused */
namespace Kodbruket\VsbridgeIndexerMsiReservation\Plugin\ResourceModel\Product;

use Divante\VsbridgeIndexerMsi\Model\GetStockIndexTableByStore;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Divante\VsbridgeIndexerMsi\Api\GetStockIdBySalesChannelCodeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Inventory
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var GetStockIndexTableByStore
     */
    private $getSockIndexTableByStore;

    /**
     * @var GetStockIdBySalesChannelCodeInterface
     */
    private $getStockIdByCode;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Inventory constructor.
     * @param GetStockIndexTableByStore             $getSockIndexTableByStore
     * @param GetStockIdBySalesChannelCodeInterface $getStockIdByCode
     * @param StoreManagerInterface                 $storeManager
     * @param ResourceConnection                    $resourceModel
     */
    public function __construct(
        GetStockIndexTableByStore $getSockIndexTableByStore,
        GetStockIdBySalesChannelCodeInterface $getStockIdByCode,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceModel
    ) {
        $this->getSockIndexTableByStore = $getSockIndexTableByStore;
        $this->getStockIdByCode = $getStockIdByCode;
        $this->storeManager = $storeManager;
        $this->resource = $resourceModel;
    }

    /**
     * @param \Divante\VsbridgeIndexerMsi\Model\ResourceModel\Product\Inventory $accountManagement
     * @param callable                                                          $proceed
     * @param int                                                               $storeId
     * @param array                                                             $skuList
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function aroundLoadInventory(
        \Divante\VsbridgeIndexerMsi\Model\ResourceModel\Product\Inventory $accountManagement,
        callable $proceed,
        int $storeId,
        array $skuList
    ) {
        return $this->getInventoryData($storeId, $skuList);
    }

    /**
     * @param \Divante\VsbridgeIndexerMsi\Model\ResourceModel\Product\Inventory $accountManagement
     * @param callable                                                          $proceed
     * @param int                                                               $storeId
     * @param array                                                             $skuList
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function aroundLoadChildrenInventory(
        \Divante\VsbridgeIndexerMsi\Model\ResourceModel\Product\Inventory $accountManagement,
        callable $proceed,
        int $storeId,
        array $skuList
    ) {
        return $this->getInventoryData($storeId, $skuList);
    }

    /**
     * @param int   $storeId
     * @param array $productIds
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getInventoryData(int $storeId, array $productIds)
    {
        $connection = $this->resource->getConnection();
        $stockItemTableName = $this->getSockIndexTableByStore->execute($storeId);
        $stockId = $this->getStockId($storeId);

        $expressionsToSelect = [
            new \Zend_Db_Expr(
                sprintf('%s.quantity + SUM(COALESCE(reservation_table.quantity, 0)) AS qty', $stockItemTableName)
            ),
            new \Zend_Db_Expr(
                sprintf(
                    'CASE 
                		WHEN %s.quantity + SUM(COALESCE(reservation_table.quantity, 0)) > 0 THEN %s.is_salable
                		WHEN SUM(reservation_table.quantity) IS NULL AND %s.quantity > 0 THEN %s.is_salable
                	ELSE 0
                	END 
                    AS is_in_stock',
                    $stockItemTableName,
                    $stockItemTableName,
                    $stockItemTableName,
                    $stockItemTableName
                )
            ),
            new \Zend_Db_Expr(
                sprintf(
                    'CASE WHEN %s.quantity + SUM(COALESCE(reservation_table.quantity, 0)) > 0 THEN %s.is_salable
                	WHEN SUM(reservation_table.quantity) IS NULL AND %s.quantity > 0 THEN %s.is_salable
                	ELSE 0
                    END AS stock_status',
                    $stockItemTableName,
                    $stockItemTableName,
                    $stockItemTableName,
                    $stockItemTableName
                )
            ),
        ];

        $select = $connection->select()
            ->from(
                $stockItemTableName,
                [
                    'sku' => IndexStructure::SKU,
                ]
            );

        $select->joinLeft(
            ['reservation_table' => $this->resource->getTableName('inventory_reservation')],
            sprintf('reservation_table.sku=%s.sku AND %d = reservation_table.stock_id', $stockItemTableName, $stockId),
            $expressionsToSelect
        );

        $select->group([ $stockItemTableName . '.' . IndexStructure::SKU ]);

        $select->where($stockItemTableName . '.' . IndexStructure::SKU . ' IN (?)', $productIds);

        return $connection->fetchAssoc($select);
    }

    /**
     * @param int $storeId
     *
     * @return int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getStockId(int $storeId)
    {
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $website = $this->storeManager->getWebsite($websiteId);

        return $this->getStockIdByCode->execute($website->getCode());
    }
}
