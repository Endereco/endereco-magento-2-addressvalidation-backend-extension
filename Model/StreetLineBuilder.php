<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Model;

class StreetLineBuilder
{
    /**
     * Build the street lines Magento's Address::setStreet() expects: street +
     * house number as the first line, the address addition (if any) as an
     * optional second line, omitted entirely when empty so setStreet()
     * doesn't receive a stray blank line.
     *
     * @param string|null $street
     * @param string|null $houseNumber
     * @param string|null $additionalInformation
     *
     * @return string[]
     */
    public function build(?string $street, ?string $houseNumber, ?string $additionalInformation): array
    {
        return array_values(array_filter([
            $street . ' ' . $houseNumber,
            $additionalInformation ?? '',
        ]));
    }
}
