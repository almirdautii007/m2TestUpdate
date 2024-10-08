<?php
namespace Scandiweb\Test\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\State;

class AddSimpleProduct implements DataPatchInterface, PatchRevertableInterface
{
    private $productFactory;
    private $productRepository;
    private $storeManager;
    private $state;

    public function __construct(
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        State $state
    ) {
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->state = $state;
    }

    public function apply()
    {
        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code is already set, no need to do anything
        }

        $store = $this->storeManager->getStore();
        $websiteId = $store->getWebsiteId();

        $product = $this->productFactory->create();
        $product->setSku('scandiweb-test-product')
                ->setName('Scandiweb Test Product')
                ->setTypeId('simple')
                ->setPrice(10.00)
                ->setAttributeSetId(4) // Default attribute set
                ->setCategoryIds([2]) // Assign to the category with ID 2
                ->setWebsiteIds([$websiteId])
                ->setVisibility(4) // Catalog, Search
                ->setStatus(1) // Enabled
                ->setStockData([
                    'use_config_manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => 100
                ]);

        $this->productRepository->save($product);
    }

    public function revert()
    {
        try {
            $product = $this->productRepository->get('scandiweb-test-product');
            $this->productRepository->delete($product);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Product does not exist, nothing to delete
        }
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
