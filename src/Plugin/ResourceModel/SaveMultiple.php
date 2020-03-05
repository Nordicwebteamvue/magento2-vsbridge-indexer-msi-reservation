<?php

namespace Kodbruket\VsbridgeIndexerMsiReservation\Plugin\ResourceModel;

use Divante\VsbridgeIndexerCatalog\Model\Indexer\ProductProcessor;
use Magento\Catalog\Model\ProductRepository;

/**
 * Save multiple
 *
 * Interception to make sure that products being reserved also get
 * triggered for reindexing.
 */
class SaveMultiple
{
    /**
     * Product processor
     *
     * @var ProductProcessor
     */
    private $productProcessor;

    /**
     * Product repository
     *
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * Constructor
     *
     * @param ProductProcessor $processor
     * @param ProductRepository $productRepository
     *
     * @return void
     */
    public function __construct(ProductProcessor $processor, ProductRepository $productRepository)
    {
        $this->productProcessor = $processor;
        $this->productRepository = $productRepository;
    }

    /**
     * After execute
     *
     * Interception to make sure that products being reserved also get
     * triggered for reindexing.
     *
     * @param \Magento\InventoryReservations\Model\ResourceModel\SaveMultiple $subject
     * @param null $result
     * @param ReservationInterface[] $reservations
     *
     * @return void
     */
    public function afterExecute(
        \Magento\InventoryReservations\Model\ResourceModel\SaveMultiple $subject,
        $result,
        array $reservations
    ) {
        foreach ($reservations as $reservation) {
            $product = $this->productRepository->get($reservation->getSku());
            $this->productProcessor->reindexRow($product->getId());
        }
    }
}
