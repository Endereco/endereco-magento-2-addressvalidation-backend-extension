<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Statuscodes implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            [
                'label' => 'address_correct',
                'value' => 'address_correct'
            ],
            [
                'label' => 'address_multiple_variants',
                'value' => 'address_multiple_variants'
            ],
            [
                'label' => 'address_not_found',
                'value' => 'address_not_found'
            ],
            [
                'label' => 'country_code_needs_correction',
                'value' => 'country_code_needs_correction'
            ],
            [
                'label' => 'country_code_correct',
                'value' => 'country_code_correct'
            ],
            [
                'label' => 'subdivision_code_needs_correction',
                'value' => 'subdivision_code_needs_correction'
            ],
            [
                'label' => 'subdivision_code_correct',
                'value' => 'subdivision_code_correct'
            ],
            [
                'label' => 'postal_code_needs_correction',
                'value' => 'postal_code_needs_correction'
            ],
            [
                'label' => 'postal_code_correct',
                'value' => 'postal_code_correct'
            ],
            [
                'label' => 'locality_needs_correction',
                'value' => 'locality_needs_correction'
            ],
            [
                'label' => 'locality_correct',
                'value' => 'locality_correct'
            ],
            [
                'label' => 'street_name_needs_correction',
                'value' => 'street_name_needs_correction'
            ],
            [
                'label' => 'street_name_correct',
                'value' => 'street_name_correct'
            ],
            [
                'label' => 'street_full_needs_correction',
                'value' => 'street_full_needs_correction'
            ],
            [
                'label' => 'street_full_correct',
                'value' => 'street_full_correct'
            ],
            [
                'label' => 'building_number_needs_correction',
                'value' => 'building_number_needs_correction'
            ],
            [
                'label' => 'building_number_correct',
                'value' => 'building_number_correct'
            ],
            [
                'label' => 'building_number_is_missing',
                'value' => 'building_number_is_missing'
            ],
            [
                'label' => 'building_number_not_found',
                'value' => 'building_number_not_found'
            ],
            [
                'label' => 'additional_info_needs_correction',
                'value' => 'additional_info_needs_correction'
            ],
            [
                'label' => 'additional_info_correct',
                'value' => 'additional_info_correct'
            ],
            [
                'label' => 'address_is_packstation',
                'value' => 'address_is_packstation'
            ],
            [
                'label' => 'address_is_postoffice',
                'value' => 'address_is_postoffice'
            ],
            [
                'label' => 'address_minor_correction',
                'value' => 'address_minor_correction'
            ],
            [
                'label' => 'address_major_correction',
                'value' => 'address_major_correction'
            ],
            [
                'label' => 'country_code_minor_correction',
                'value' => 'country_code_minor_correction'
            ],
            [
                'label' => 'country_code_major_correction',
                'value' => 'country_code_major_correction'
            ],
            [
                'label' => 'subdivision_code_minor_correction',
                'value' => 'subdivision_code_minor_correction'
            ],
            [
                'label' => 'subdivision_code_major_correction',
                'value' => 'subdivision_code_major_correction'
            ],
            [
                'label' => 'postal_code_minor_correction',
                'value' => 'postal_code_minor_correction'
            ],
            [
                'label' => 'postal_code_major_correction',
                'value' => 'postal_code_major_correction'
            ],
            [
                'label' => 'locality_minor_correction',
                'value' => 'locality_minor_correction'
            ],
            [
                'label' => 'locality_major_correction',
                'value' => 'locality_major_correction'
            ],
            [
                'label' => 'street_name_minor_correction',
                'value' => 'street_name_minor_correction'
            ],
            [
                'label' => 'street_name_major_correction',
                'value' => 'street_name_major_correction'
            ],
            [
                'label' => 'building_number_minor_correction',
                'value' => 'building_number_minor_correction'
            ],
            [
                'label' => 'building_number_major_correction',
                'value' => 'building_number_major_correction'
            ]
        ];
    }
}
