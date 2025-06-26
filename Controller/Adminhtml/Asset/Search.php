<?php

namespace Code2stay\Importproduct\Controller\Adminhtml\Asset;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Search extends Action
{
    protected $jsonFactory;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
    }

    /**
     * Controller action to return filtered assets.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $this->_logger->debug('Controller is being called!');

        // Get the search query parameter
        $searchQuery = $this->getRequest()->getParam('q', '');

        // Sample data: You can fetch this from your model or any other source
        $assets = [
            ['value' => 'asset_a', 'label' => 'Asset A'],
            ['value' => 'asset_b', 'label' => 'Asset B'],
            ['value' => 'asset_c', 'label' => 'Asset C'],
            // You can fetch assets dynamically based on $searchQuery
        ];

        // Filter results based on search query
        if ($searchQuery) {
            $assets = array_filter($assets, function ($asset) use ($searchQuery) {
                return stripos($asset['label'], $searchQuery) !== false;
            });
        }

        // Return filtered options as a JSON response
        $result = $this->jsonFactory->create();
        return $result->setData(['items' => array_values($assets)]);
    }
}
