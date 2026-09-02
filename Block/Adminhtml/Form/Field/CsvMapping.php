<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class Ranges
 */
class CsvMapping extends AbstractFieldArray
{
    /**
     * @var ValueColumn
     */
    private $valueRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('header', [
            'label' => __('Column Header'),
            'class' => 'required-entry textarea',
            'style' => 'textarea; min-width:150px'
        ]);
        $this->addColumn('value', [
            'label'    => __('Data'),
            'renderer' => $this->getValueRenderer(),
            'class'    => 'required-entry'
        ]);
        $this->_addAfter       = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $value = $row->getValue();
        if ($value !== null) {
            $hash = 'option_' . $this->getValueRenderer()->calcOptionHash($value);
            $options[$hash] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @return ValueColumn
     * @throws LocalizedException
     */
    private function getValueRenderer()
    {
        if (!$this->valueRenderer) {
            $this->valueRenderer = $this->getLayout()->createBlock(
                ValueColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->valueRenderer;
    }
}
