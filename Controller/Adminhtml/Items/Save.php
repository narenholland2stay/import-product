<?php

declare(strict_types=1);

namespace Code2stay\Importproduct\Controller\Adminhtml\Items;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Psr\Log\LoggerInterface;
use Magento\Backend\Model\Session;
use Magento\Framework\File\Csv;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem\Driver\File;
use Code2stay\Importproduct\Model\ImportproductFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Framework\Filesystem;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeSetRepositoryInterface;

/**
 * Controller for importing products via CSV in Magento 2 Admin.
 *
 * This controller handles the upload and processing of a CSV file containing product data.
 * Only attributes defined in ALLOWED_ATTRIBUTES are imported or updated.
 * The controller ensures products are created or updated according to business rules,
 * and provides feedback via Magento's message manager.
 *
 * PHP version 8.2
 * Magento version 2.4.8-p1
 *
 * @category   Code2stay
 * @package    Code2stay_Importproduct
 * @author     narendra@Holland2stay.com
 */
class Save extends Action
{
    /**
     * List of allowed product attributes for import/update (case-insensitive).
     */
    public const ALLOWED_ATTRIBUTES = [
        'sku',
        'name',
        'type_of_contract',
        'parking_available',
        'parking_id',
        'parking_code',
        'parking_status',
        'storage_available',
        'storage_id',
        'storage_code',
        'storage_status',
        'start_unit_date',
        'energy_link',
        'energy_label',
        'energy_index',
        'energy_start',
        'energy_end',
        'publishing_way',
        'reserved_comment',
        'contract_template',
        'utilities_in_contract',
        'contract_custom_text',
        'price_analysis_text',
        'supplies_website',
        'service_costs_website',
        'description',
        'book_now_text',
        'offer_text',
        'offer_text_two',
        'location',
        'income_requirements',
        'short_description',
        'additional_attributes'
    ];

    protected ProductRepositoryInterface $productRepository;
    protected StoreManagerInterface $storeManager;
    protected UploaderFactory $uploaderFactory;
    protected Filesystem $filesystem;
    protected DirectoryList $directoryList;
    protected File $file;
    protected Csv $csv;
    protected Session $session;
    protected ImportproductFactory $importproductFactory;
    protected ProductFactory $productFactory;
    protected ProductResourceModel $productResourceModel;
    protected StockRegistryInterface $stockRegistry;
    protected LoggerInterface $logger;
    protected AttributeSetRepositoryInterface $attributeSetRepository;

    /**
     * Constructor for dependency injection.
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        Filesystem $filesystem,
        DirectoryList $directoryList,
        UploaderFactory $uploaderFactory,
        StockRegistryInterface $stockRegistry,
        File $file,
        Csv $csv,
        Session $session,
        ImportproductFactory $importproductFactory,
        ProductFactory $productFactory,
        ProductResourceModel $productResourceModel,
        AttributeSetRepositoryInterface $attributeSetRepository
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
        $this->uploaderFactory = $uploaderFactory;
        $this->logger = $logger;
        $this->file = $file;
        $this->csv = $csv;
        $this->stockRegistry = $stockRegistry;
        $this->session = $session;
        $this->importproductFactory = $importproductFactory;
        $this->productFactory = $productFactory;
        $this->productResourceModel = $productResourceModel;
        $this->attributeSetRepository = $attributeSetRepository;
    }

    // Add this helper method to get the default attribute set ID:
    protected function getDefaultAttributeSetId(): int
    {
        $productEntityType = Product::ENTITY;
        $attributeSetId = $this->productFactory->create()->getResource()->getEntityType()->getDefaultAttributeSetId();
        return (int)$attributeSetId;
    }

    /**
     * Main controller action for handling the CSV import.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        if ($this->getRequest()->getPostValue()) {
            try {
                $fileData = $this->getRequest()->getFiles('csv_file');
                if (isset($fileData['name']) && $fileData['name'] !== '') {
                    try {
                        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
                        $destinationPath = $mediaDirectory->getAbsolutePath('code2stay/importproduct/');

                        $uploader = $this->uploaderFactory->create(['fileId' => 'csv_file']);
                        $uploader->setAllowedExtensions(['csv']);
                        $uploader->setAllowRenameFiles(true);
                        $uploader->setFilesDispersion(false);

                        $result = $uploader->save($destinationPath);
                        if ($result) {
                            $csvPath = $destinationPath . $result['file'];
                            $csvData = $this->readCsvFile($csvPath);
                            if ($csvData) {
                                $headers = array_map(
                                    static fn($h) => strtolower(trim($h)),
                                    array_shift($csvData)
                                );
                                foreach ($csvData as $row) {
                                    $productData = $this->mapCsvToProductData($headers, $row);
                                    $product = $this->productFactory->create();
                                    $existingProduct = $product->getCollection()
                                        ->addFieldToFilter('sku', $productData['sku'] ?? '')
                                        ->getFirstItem();

                                    if ($existingProduct->getId()) {
                                        $this->updateProduct($existingProduct, $productData);
                                    }
                                }
                            }
                        } else {
                            $this->messageManager->addError(__('Error uploading CSV file.'));
                        }
                    } catch (\Exception $e) {
                        $this->messageManager->addError(__('Error uploading CSV file: %1', $e->getMessage()));
                        return $this->_redirect('code2stay_importproduct/*/new');
                    }
                }
                $this->messageManager->addSuccess(__('CSV processed successfully.'));
                return $this->_redirect('code2stay_importproduct/*/new');
            } catch (\Exception $e) {
                $this->messageManager->addError(__('Something went wrong while saving the item data.'));
                return $this->_redirect('code2stay_importproduct/*/new');
            }
        }
        return $this->_redirect('code2stay_importproduct/*/new');
    }

    /**
     * Reads a CSV file and returns its data as an array.
     *
     * @param string $filePath
     * @return array
     */
    protected function readCsvFile(string $filePath): array
    {
        $data = [];
        try {
            $csvData = $this->csv->getData($filePath);
            foreach ($csvData as $row) {
                $data[] = $row;
            }
        } catch (\Exception $e) {
            $this->messageManager->addError(__('Error reading CSV file: %1', $e->getMessage()));
        }
        return $data;
    }

    /**
     * Maps CSV row data to allowed product attributes.
     *
     * @param array $headers
     * @param array $row
     * @return array
     */
    protected function mapCsvToProductData(array $headers, array $row): array
    {
        $productData = [];

        $alphanumericAttributes = ['name', 'parking_id', 'parking_code'];
        foreach ($headers as $index => $header) {
            // Only import attribute if value is present and not empty
            if (
                in_array($header, self::ALLOWED_ATTRIBUTES, true)
                && isset($row[$index])
                && $row[$index] !== ''
            ) {
                $value = trim($row[$index]);
                // Validate parking_id: alphanumeric only, no spaces or special characters
                if (in_array($header, $alphanumericAttributes, true) && !preg_match('/^[a-zA-Z0-9]+$/', $row[$index])) {
                    throw new \InvalidArgumentException(
                        (string)__("Invalid value for %1: \"%2\". Only alphanumeric characters (no spaces or special characters) are allowed.", $header, $row[$index])
                    );
                }
                $productData[$header] = $row[$index];
            }
        }

        // SKU validation: must be present and alphanumeric only
        if (empty($productData['sku'])) {
            throw new \InvalidArgumentException((string)__('SKU is mandatory in the CSV file.'));
        }
        if (!preg_match('/^[a-zA-Z0-9]+$/', $productData['sku'])) {
            throw new \InvalidArgumentException((string)__('SKU "%1" is invalid. Only alphanumeric characters are allowed.', $productData['sku']));
        }

        // Convert type_of_contract,parking_status label to option ID
        $selectAttributes = ['type_of_contract', 'parking_status'];

        foreach ($selectAttributes as $selectAttr) {
            if (!empty($productData[$selectAttr])) {
                $optionId = $this->getAttributeOptionId($selectAttr, $productData[$selectAttr]);
                if ($optionId === null) {
                    throw new \InvalidArgumentException((string)__('Invalid value for %1: "%2"', $selectAttr, $productData[$selectAttr]));
                }
                $productData[$selectAttr] = $optionId;
            }
        }

        // Validate parking_available as boolean (accepts 0, 1, true, false, yes, no)
        $booleanAttributes = ['parking_available', 'storage_available'];
        $allowedTrue = ['1', 'true', 'yes'];
        $allowedFalse = ['0', 'false', 'no'];

        foreach ($booleanAttributes as $boolAttr) {
            if (isset($productData[$boolAttr])) {
                $val = strtolower(trim((string)$productData[$boolAttr]));
                if (in_array($val, $allowedTrue, true)) {
                    $productData[$boolAttr] = 1;
                } elseif (in_array($val, $allowedFalse, true)) {
                    $productData[$boolAttr] = 0;
                } else {
                    throw new \InvalidArgumentException(
                        (string)__("Invalid value for %1: \"%2\". Allowed: 1, 0, true, false, yes, no.", $boolAttr, $productData[$boolAttr])
                    );
                }
            }
        }

        return $productData;
    }

    /**
     * Retrieves the option ID for a given attribute code and label.
     *
     * @param string $attributeCode The code of the attribute to search for.
     * @param string $label The label of the attribute option to find.
     * @return int|null The ID of the attribute option if found, or null if not found.
     */
    protected function getAttributeOptionId(string $attributeCode, string $label): ?int
    {
        /** @var \Magento\Eav\Model\Config $eavConfig */
        $eavConfig = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Eav\Model\Config::class);
        try {
            $attribute = $eavConfig->getAttribute('catalog_product', $attributeCode);
            foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                if (strcasecmp(trim($option['label']), trim($label)) === 0) {
                    return (int)$option['value'];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Error fetching option ID for $attributeCode: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Generates a unique URL key for a product based on its name.
     *
     * @param string $name
     * @return string
     */
    protected function generateUniqueUrlKey(string $name): string
    {
        $urlKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $productCollection = $this->productFactory->create()->getCollection();
        $productCollection->addFieldToFilter('url_key', $urlKey);
        if ($productCollection->getSize() > 0) {
            $urlKey = $this->generateRandomUrlKey();
        }
        return $urlKey;
    }

    /**
     * Generates a random string for use as a URL key.
     *
     * @return string
     */
    protected function generateRandomUrlKey(): string
    {
        return bin2hex(random_bytes(8));
    }


    /**
     * Updates the given product with the provided product data.
     *
     * @param \Magento\Catalog\Model\Product $product The product instance to update.
     * @param array $productData An associative array of product data to apply.
     *
     * @return void
     */
    protected function updateProduct($product, array $productData): void
    {
        try {
            foreach (self::ALLOWED_ATTRIBUTES as $attribute) {
                if (isset($productData[$attribute])) {
                    $product->setData($attribute, $productData[$attribute]);
                }
            }
            $product->setAttributeSetId($this->getDefaultAttributeSetId());
            $product->setTypeId('simple');
            $product->setStatus(1);
            $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);

            $validWebsiteIds = [];

            // Only assign valid website IDs
            foreach ($this->storeManager->getWebsites() as $website) {
                $stores = $website->getStores();
                $hasValidStore = false;
                foreach ($stores as $store) {
                    // Exclude admin store (store_id = 0) and check if store is active
                    if ($store->getId() != 0 && $store->getIsActive()) {
                        $hasValidStore = true;
                        break;
                    }
                }
                if ($hasValidStore) {
                    $validWebsiteIds[] = $website->getId();
                }
            }
            if (empty($validWebsiteIds)) {
                $this->messageManager->addError(__('No valid websites available for product update.'));
                return;
            }
            $product->setWebsiteIds($validWebsiteIds);

            $product->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 1,
            ]);

            if (!empty($productData['name'])) {
                $urlKey = $this->generateUniqueUrlKey($productData['name']);
                $product->setUrlKey($urlKey);
            }
            $this->productRepository->save($product);

            $stockItem = $this->stockRegistry->getStockItemBySku($productData['sku'] ?? '');
            if ($stockItem) {
                $stockItem->setQty(1);
                $stockItem->setIsInStock(true);
                $this->stockRegistry->updateStockItemBySku($productData['sku'] ?? '', $stockItem);
            }

            $this->messageManager->addSuccess(__('Product updated successfully: %1', $productData['sku'] ?? ''));
        } catch (\Exception $e) {
            $this->logger->error("Error updating product with SKU: " . ($productData['sku'] ?? '') . " | Error: " . $e->getMessage());
            $this->messageManager->addError(__('Error updating product with SKU %1: %2', $productData['sku'] ?? '', $e->getMessage()));
        }
    }
}
