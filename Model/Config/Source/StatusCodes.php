<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class StatusCodes implements OptionSourceInterface
{
    /**
     * Endereco API status codes available for the "manual review" trigger.
     * Labels are wrapped in __() so installations can localise the wording,
     * but typically these stay as identifiers in the admin UI.
     */
    private const CODES = [
        'address_correct',
        'address_multiple_variants',
        'address_not_found',
        'country_code_needs_correction',
        'country_code_correct',
        'subdivision_code_needs_correction',
        'subdivision_code_correct',
        'postal_code_needs_correction',
        'postal_code_correct',
        'locality_needs_correction',
        'locality_correct',
        'street_name_needs_correction',
        'street_name_correct',
        'street_full_needs_correction',
        'street_full_correct',
        'building_number_needs_correction',
        'building_number_correct',
        'building_number_is_missing',
        'building_number_not_found',
        'additional_info_needs_correction',
        'additional_info_correct',
        'address_is_packstation',
        'address_is_postoffice',
        'address_minor_correction',
        'address_major_correction',
        'country_code_minor_correction',
        'country_code_major_correction',
        'subdivision_code_minor_correction',
        'subdivision_code_major_correction',
        'postal_code_minor_correction',
        'postal_code_major_correction',
        'locality_minor_correction',
        'locality_major_correction',
        'street_name_minor_correction',
        'street_name_major_correction',
        'building_number_minor_correction',
        'building_number_major_correction',
    ];

    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::CODES as $code) {
            $options[] = [
                'label' => __($code),
                'value' => $code,
            ];
        }

        return $options;
    }
}
