<?php
namespace Code2stay\Importproduct\Controller\Adminhtml\Items;

class Delete extends \Code2stay\Importproduct\Controller\Adminhtml\Items
{

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            try {
                $model = $this->_objectManager->create('Code2stay\Importproduct\Model\Importproduct');
                $model->load($id);
                $model->delete();
                $this->messageManager->addSuccess(__('You deleted the item.'));
                $this->_redirect('code2stay_importproduct/*/');
                return $this->_redirect('code2stay_importproduct/*/');
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addError(
                    __('We can\'t delete item right now. Please review the log and try again.')
                );
                $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                return $this->_redirect('code2stay_importproduct/*/edit', ['id' => $this->getRequest()->getParam('id')]);

            }
        }
        $this->messageManager->addError(__('We can\'t find a item to delete.'));
           return $this->_redirect('code2stay_importproduct/*/');
    }
}
