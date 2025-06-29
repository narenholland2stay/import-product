<?php
/**
 * @category   Code2stay
 * @package    Code2stay_Importproduct
 * @author     rajnitish625@gmail.com
 * @copyright  This file was generated by using Module Creator(http://code.vky.co.in/magento-2-module-creator/) provided by VKY <viky.031290@gmail.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Code2stay\Importproduct\Block\Adminhtml\Items\Edit;

class Tabs extends \Magento\Backend\Block\Widget\Tabs
{
    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('code2stay_importproduct_items_edit_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Import Products'));
    }
}
