<?php

namespace Vendor\Module\Patch;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

/**
 * Class CreateGripTrainerProduct
 * @package Vendor\Module\Patch
 */
class CreateGripTrainerProduct implements DataPatchInterface
{
    protected ModuleDataSetupInterface $setup;
    protected ProductInterfaceFactory $productInterfaceFactory;
    protected ProductRepositoryInterface $productRepository;
    protected State $appState;
    protected EavSetup $eavSetup;
    protected StoreManagerInterface $storeManager;
    protected SourceItemInterfaceFactory $sourceItemFactory;
    protected SourceItemsSaveInterface $sourceItemsSaveInterface;
    protected CategoryLinkManagementInterface $categoryLink;
    protected array $sourceItems = [];

    /**
     * CreateGripTrainerProduct constructor.
     *
     * @param ModuleDataSetupInterface $setup
     * @param ProductInterfaceFactory $productInterfaceFactory
     * @param ProductRepositoryInterface $productRepository
     * @param State $appState
     * @param StoreManagerInterface $storeManager
     * @param EavSetup $eavSetup
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param SourceItemsSaveInterface $sourceItemsSaveInterface
     * @param CategoryLinkManagementInterface $categoryLink
     */
    public function __construct(
        ModuleDataSetupInterface $setup,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepository,
        State $appState,
        StoreManagerInterface $storeManager,
        EavSetup $eavSetup,
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
        CategoryLinkManagementInterface $categoryLink
    ) {
        $this->setup = $setup;
        $this->appState = $appState;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepository = $productRepository;
        $this->eavSetup = $eavSetup;
        $this->storeManager = $storeManager;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->categoryLink = $categoryLink;
    }

    /**
     * Apply the data patch
     *
     * @return void
     */
    public function apply(): void
    {
        // Use area emulation to avoid exceptions
        $this->appState->emulateAreaCode('adminhtml', function () {
            $this->execute();
        });
    }

    /**
     * Execute the product creation
     *
     * @return void
     */
    public function execute(): void
    {
        $sku = 'grip-trainer';

        // Check if the product already exists by SKU
        try {
            if ($this->productRepository->getIdBySku($sku)) {
                return; // Skip if the product already exists
            }
        } catch (NoSuchEntityException $e) {
            // Product does not exist, we can create it
        }

        // Create the product
        /** @var ProductInterface $product */
        $product = $this->productInterfaceFactory->create();
        $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');
        $websiteId = $this->storeManager->getStore()->getWebsiteId();

        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId($attributeSetId)
            ->setSku($sku)
            ->setName('Grip Trainer')
            ->setPrice(9.99)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setWebsiteIds([$websiteId])
            ->setStockData([
                'use_config_manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 100 
            ]);

        // Save the product
        $this->productRepository->save($product);

        // Create and save source item with quantity
        /** @var SourceItemInterface $sourceItem */
        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode('default')
            ->setSku($product->getSku())
            ->setQuantity(100)
            ->setStatus(SourceItemInterface::STATUS_IN_STOCK);

        $this->sourceItems[] = $sourceItem;
        $this->sourceItemsSaveInterface->execute($this->sourceItems);

        // Assign to category (adjust the category ID as needed)
        $this->categoryLink->assignProductToCategories($product->getSku(), [2]);
    }

    /**
     * Revert the data patch
     *
     * @return void
     */
    public function revert(): void
    {
        $sku = 'grip-trainer';

        // Remove the product if it exists
        try {
            if ($this->productRepository->getIdBySku($sku)) {
                $product = $this->productRepository->getById($this->productRepository->getIdBySku($sku));
                $this->productRepository->delete($product);
            }
        } catch (NoSuchEntityException $e) {
            // Product does not exist, nothing to revert
        }
    }

    /**
     * Get dependencies
     *
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get aliases
     *
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }
}
