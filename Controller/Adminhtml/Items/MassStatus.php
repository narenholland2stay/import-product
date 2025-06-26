<?php

namespace Code2stay\Importproduct\Controller\Adminhtml\Items;

use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Code2stay\Importproduct\Model\ResourceModel\Importproduct\CollectionFactory; // Corrected Namespace

class MassDelete extends \Magento\Backend\App\Action
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function execute()
    {
        // Get filtered collection
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();

        // Check if collection is not empty before performing delete
        if ($collectionSize > 0) {
            try {
                // Loop through each record and delete it
                foreach ($collection as $record) {
                    $record->delete();
                }

                // Success message
                $this->messageManager->addSuccess(__('A total of %1 record(s) have been deleted.', $collectionSize));
            } catch (\Exception $e) {
                // Error handling
                $this->messageManager->addError(__('An error occurred while deleting the records.'));
                
            }
        } else {
            // If no records are selected for deletion
            $this->messageManager->addError(__('No records selected for deletion.'));
        }

        // Redirect to the grid page
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
