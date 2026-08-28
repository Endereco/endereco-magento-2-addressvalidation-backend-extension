<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Model;

use Parc\AddressValidation\Api\Data\AddressValidationInterface;

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

    /**
     * Same as build(), but resolves the street lines from a validation record
     * following the documented priority: manual correction -> API suggestion
     * -> original address.
     *
     * orig_street_full can't go through build(): it's the customer's checkout
     * input as one already-combined string (street + house number together,
     * however they typed it), not a separate street/house-number pair like
     * manu_ and api_ are. Concatenating a house number onto it again would
     * corrupt it, so this only falls back to it as a single, whole line, and
     * only once manual correction and API suggestion both have nothing at
     * all (checked via the underlying street field, not the composed result,
     * since a real street with a genuinely empty house number is still a
     * value worth using, not a reason to fall back further).
     *
     * @param AddressValidationInterface $addressValidation
     *
     * @return string[]
     */
    public function buildFromResolved(AddressValidationInterface $addressValidation): array
    {
        if ($addressValidation->getManuStreet() !== null || $addressValidation->getApiStreet() !== null) {
            return $this->build(
                $addressValidation->getResolvedStreet(),
                $addressValidation->getResolvedHouseNumber(),
                $addressValidation->getResolvedAdditionalInformation()
            );
        }

        // Not array_filter(): its default callback treats the string "0" as
        // falsy and would silently drop a street value that happens to be
        // exactly "0" - an explicit null/empty check doesn't have that trap.
        $origStreetFull = $addressValidation->getOrigStreetFull();

        return $origStreetFull !== null && $origStreetFull !== '' ? [$origStreetFull] : [];
    }
}
