<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Model;

use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;

class RegionResolver
{
    /**
     * Magento ships with non-ISO region codes for some countries (e.g. "BAY"
     * instead of ISO "BY" for Bayern, "WI" instead of ISO "9" for Wien; ES and
     * FI store a plain name in the code column instead of any code at all).
     * Map ISO 3166-2 subdivision codes to canonical region names so we can
     * look up the Magento region_id at runtime by name - which is stable
     * across installations. Matches against Region::getDefaultName(), not
     * getCode(), so this is unaffected by garbled encodings in Magento's own
     * seed data for the "code" column (confirmed present for ES - see below).
     *
     * DE/AT ported from Endereco\Addressvalidation\Model\ConfigProvider, which
     * solves the identical problem for the checkout module's subdivision
     * dropdown. ES/FI added separately (see caveats below); LV, despite also
     * looking name-only in Magento's UI, already carries proper "LV-XX" ISO
     * codes for every real region and needs no override entry.
     *
     * Known limitations, not fixable by adding more entries here:
     * - Magento's own directory_country_region.code column for ES is
     *   corrupted (e.g. "A Coru\xd1\xd1a"-style mojibake instead of "A Coruña") -
     *   harmless for us since we never read that column, only default_name,
     *   but flagged here because it could bite the next person who tries to
     *   extend this table by copy-pasting from the "code" column instead.
     * - Magento's FI region list still carries "Itä-Uusimaa" as its own
     *   region, a division that was merged into "Uusimaa" (FI-18) in a 2011
     *   Finnish administrative reform and has had no ISO 3166-2 code since.
     *   Left unmapped below - any address the API resolves to that historical
     *   region can never get a region_id from us, only from the admin dropdown.
     */
    private const ISO_SUBDIVISION_NAMES = [
        'DE-BB' => ['country' => 'DE', 'name' => 'Brandenburg'],
        'DE-BE' => ['country' => 'DE', 'name' => 'Berlin'],
        'DE-BW' => ['country' => 'DE', 'name' => 'Baden-Württemberg'],
        'DE-BY' => ['country' => 'DE', 'name' => 'Bayern'],
        'DE-HB' => ['country' => 'DE', 'name' => 'Bremen'],
        'DE-HE' => ['country' => 'DE', 'name' => 'Hessen'],
        'DE-HH' => ['country' => 'DE', 'name' => 'Hamburg'],
        'DE-MV' => ['country' => 'DE', 'name' => 'Mecklenburg-Vorpommern'],
        'DE-NI' => ['country' => 'DE', 'name' => 'Niedersachsen'],
        'DE-NW' => ['country' => 'DE', 'name' => 'Nordrhein-Westfalen'],
        'DE-RP' => ['country' => 'DE', 'name' => 'Rheinland-Pfalz'],
        'DE-SH' => ['country' => 'DE', 'name' => 'Schleswig-Holstein'],
        'DE-SL' => ['country' => 'DE', 'name' => 'Saarland'],
        'DE-SN' => ['country' => 'DE', 'name' => 'Sachsen'],
        'DE-ST' => ['country' => 'DE', 'name' => 'Sachsen-Anhalt'],
        'DE-TH' => ['country' => 'DE', 'name' => 'Thüringen'],
        'AT-1'  => ['country' => 'AT', 'name' => 'Burgenland'],
        'AT-2'  => ['country' => 'AT', 'name' => 'Kärnten'],
        'AT-3'  => ['country' => 'AT', 'name' => 'Niederösterreich'],
        'AT-4'  => ['country' => 'AT', 'name' => 'Oberösterreich'],
        'AT-5'  => ['country' => 'AT', 'name' => 'Salzburg'],
        'AT-6'  => ['country' => 'AT', 'name' => 'Steiermark'],
        'AT-7'  => ['country' => 'AT', 'name' => 'Tirol'],
        'AT-8'  => ['country' => 'AT', 'name' => 'Vorarlberg'],
        'AT-9'  => ['country' => 'AT', 'name' => 'Wien'],
        'ES-C'  => ['country' => 'ES', 'name' => 'A Coruña'],
        'ES-VI' => ['country' => 'ES', 'name' => 'Alava'],
        'ES-AB' => ['country' => 'ES', 'name' => 'Albacete'],
        'ES-A'  => ['country' => 'ES', 'name' => 'Alicante'],
        'ES-AL' => ['country' => 'ES', 'name' => 'Almeria'],
        'ES-O'  => ['country' => 'ES', 'name' => 'Asturias'],
        'ES-AV' => ['country' => 'ES', 'name' => 'Avila'],
        'ES-BA' => ['country' => 'ES', 'name' => 'Badajoz'],
        'ES-PM' => ['country' => 'ES', 'name' => 'Baleares'],
        'ES-B'  => ['country' => 'ES', 'name' => 'Barcelona'],
        'ES-BU' => ['country' => 'ES', 'name' => 'Burgos'],
        'ES-CC' => ['country' => 'ES', 'name' => 'Caceres'],
        'ES-CA' => ['country' => 'ES', 'name' => 'Cadiz'],
        'ES-S'  => ['country' => 'ES', 'name' => 'Cantabria'],
        'ES-CS' => ['country' => 'ES', 'name' => 'Castellon'],
        'ES-CE' => ['country' => 'ES', 'name' => 'Ceuta'],
        'ES-CR' => ['country' => 'ES', 'name' => 'Ciudad Real'],
        'ES-CO' => ['country' => 'ES', 'name' => 'Cordoba'],
        'ES-CU' => ['country' => 'ES', 'name' => 'Cuenca'],
        'ES-GI' => ['country' => 'ES', 'name' => 'Girona'],
        'ES-GR' => ['country' => 'ES', 'name' => 'Granada'],
        'ES-GU' => ['country' => 'ES', 'name' => 'Guadalajara'],
        'ES-SS' => ['country' => 'ES', 'name' => 'Guipuzcoa'],
        'ES-H'  => ['country' => 'ES', 'name' => 'Huelva'],
        'ES-HU' => ['country' => 'ES', 'name' => 'Huesca'],
        'ES-J'  => ['country' => 'ES', 'name' => 'Jaen'],
        'ES-LO' => ['country' => 'ES', 'name' => 'La Rioja'],
        'ES-GC' => ['country' => 'ES', 'name' => 'Las Palmas'],
        'ES-LE' => ['country' => 'ES', 'name' => 'Leon'],
        'ES-L'  => ['country' => 'ES', 'name' => 'Lleida'],
        'ES-LU' => ['country' => 'ES', 'name' => 'Lugo'],
        'ES-M'  => ['country' => 'ES', 'name' => 'Madrid'],
        'ES-MA' => ['country' => 'ES', 'name' => 'Malaga'],
        'ES-ML' => ['country' => 'ES', 'name' => 'Melilla'],
        'ES-MU' => ['country' => 'ES', 'name' => 'Murcia'],
        'ES-NA' => ['country' => 'ES', 'name' => 'Navarra'],
        'ES-OR' => ['country' => 'ES', 'name' => 'Ourense'],
        'ES-P'  => ['country' => 'ES', 'name' => 'Palencia'],
        'ES-PO' => ['country' => 'ES', 'name' => 'Pontevedra'],
        'ES-SA' => ['country' => 'ES', 'name' => 'Salamanca'],
        'ES-TF' => ['country' => 'ES', 'name' => 'Santa Cruz de Tenerife'],
        'ES-SG' => ['country' => 'ES', 'name' => 'Segovia'],
        'ES-SE' => ['country' => 'ES', 'name' => 'Sevilla'],
        'ES-SO' => ['country' => 'ES', 'name' => 'Soria'],
        'ES-T'  => ['country' => 'ES', 'name' => 'Tarragona'],
        'ES-TE' => ['country' => 'ES', 'name' => 'Teruel'],
        'ES-TO' => ['country' => 'ES', 'name' => 'Toledo'],
        'ES-V'  => ['country' => 'ES', 'name' => 'Valencia'],
        'ES-VA' => ['country' => 'ES', 'name' => 'Valladolid'],
        'ES-BI' => ['country' => 'ES', 'name' => 'Vizcaya'],
        'ES-ZA' => ['country' => 'ES', 'name' => 'Zamora'],
        'ES-Z'  => ['country' => 'ES', 'name' => 'Zaragoza'],
        'FI-01' => ['country' => 'FI', 'name' => 'Ahvenanmaa'],
        'FI-02' => ['country' => 'FI', 'name' => 'Etelä-Karjala'],
        'FI-03' => ['country' => 'FI', 'name' => 'Etelä-Pohjanmaa'],
        'FI-04' => ['country' => 'FI', 'name' => 'Etelä-Savo'],
        'FI-05' => ['country' => 'FI', 'name' => 'Kainuu'],
        'FI-06' => ['country' => 'FI', 'name' => 'Kanta-Häme'],
        'FI-07' => ['country' => 'FI', 'name' => 'Keski-Pohjanmaa'],
        'FI-08' => ['country' => 'FI', 'name' => 'Keski-Suomi'],
        'FI-09' => ['country' => 'FI', 'name' => 'Kymenlaakso'],
        'FI-10' => ['country' => 'FI', 'name' => 'Lappi'],
        'FI-11' => ['country' => 'FI', 'name' => 'Pirkanmaa'],
        'FI-12' => ['country' => 'FI', 'name' => 'Pohjanmaa'],
        'FI-13' => ['country' => 'FI', 'name' => 'Pohjois-Karjala'],
        'FI-14' => ['country' => 'FI', 'name' => 'Pohjois-Pohjanmaa'],
        'FI-15' => ['country' => 'FI', 'name' => 'Pohjois-Savo'],
        'FI-16' => ['country' => 'FI', 'name' => 'Päijät-Häme'],
        'FI-17' => ['country' => 'FI', 'name' => 'Satakunta'],
        'FI-18' => ['country' => 'FI', 'name' => 'Uusimaa'],
        'FI-19' => ['country' => 'FI', 'name' => 'Varsinais-Suomi'],
    ];

    /**
     * @var RegionCollectionFactory
     */
    private RegionCollectionFactory $regionCollectionFactory;

    /**
     * Lazily built ISO 3166-2 code => Magento region_id map. Built once per
     * request/cron-run, not once per order, since it requires loading the
     * full region collection.
     *
     * @var array<string, int>|null
     */
    private ?array $subdivisionMapping = null;

    /**
     * Lazily built region_id => default name map, built alongside the
     * subdivision mapping from the same region collection pass.
     *
     * @var array<int, string>|null
     */
    private ?array $namesByRegionId = null;

    /**
     * Lazily built region_id => ISO 3166-2 code map - the reverse of
     * $subdivisionMapping, for displaying an existing region_id (e.g. the
     * order's original region) in the same portable format the API uses.
     *
     * @var array<int, string>|null
     */
    private ?array $codesByRegionId = null;

    /**
     * @param RegionCollectionFactory $regionCollectionFactory
     */
    public function __construct(RegionCollectionFactory $regionCollectionFactory)
    {
        $this->regionCollectionFactory = $regionCollectionFactory;
    }

    /**
     * Resolve a Magento region_id from an ISO 3166-2 subdivision code (e.g. "DE-BY").
     *
     * Returns null when no match can be found - this is expected for countries
     * where Magento's stock region data doesn't follow ISO codes and isn't
     * covered by the override table above (e.g. Spain, Italy). Callers must
     * not overwrite an existing region_id with null in that case.
     *
     * @param string      $countryId
     * @param string|null $subdivisionCode
     *
     * @return int|null
     */
    public function resolveRegionId(string $countryId, ?string $subdivisionCode): ?int
    {
        if (!$subdivisionCode) {
            return null;
        }

        $this->buildMappings();

        return $this->subdivisionMapping[strtoupper($subdivisionCode)] ?? null;
    }

    /**
     * Look up a region's default display name by its Magento region_id.
     *
     * @param int|null $regionId
     *
     * @return string|null
     */
    public function getRegionName(?int $regionId): ?string
    {
        if ($regionId === null) {
            return null;
        }

        $this->buildMappings();

        return $this->namesByRegionId[$regionId] ?? null;
    }

    /**
     * Reverse of resolveRegionId(): look up the ISO 3166-2 subdivision code
     * for an existing Magento region_id (e.g. to display the order's original
     * region in the same format the API returns, for side-by-side comparison).
     *
     * Returns null when the region isn't covered by the ISO mapping (same
     * countries/limitations as resolveRegionId()) - most commonly because
     * Magento's region "code" for that country is a full name, not a code.
     *
     * @param int|null $regionId
     *
     * @return string|null
     */
    public function getSubdivisionCode(?int $regionId): ?string
    {
        if ($regionId === null) {
            return null;
        }

        $this->buildMappings();

        return $this->codesByRegionId[$regionId] ?? null;
    }

    /**
     * Set region_id together with its matching region name on an order address.
     * Magento does not keep these two fields in sync on its own - setting
     * region_id alone leaves the free-text region name stale. No-op when
     * $regionId is null so callers can't accidentally clobber an existing,
     * correct region with an unresolved one.
     *
     * @param OrderAddressInterface $address
     * @param int|null              $regionId
     *
     * @return void
     */
    public function applyRegion(OrderAddressInterface $address, ?int $regionId): void
    {
        if ($regionId === null) {
            return;
        }

        $address->setRegionId($regionId);
        $address->setRegion($this->getRegionName($regionId) ?? '');
    }

    private function buildMappings(): void
    {
        if ($this->subdivisionMapping !== null) {
            return;
        }

        $nameBasedCountries = array_unique(
            array_column(self::ISO_SUBDIVISION_NAMES, 'country')
        );

        $nameToId        = [];
        $mapping         = [];
        $namesByRegionId = [];
        foreach ($this->regionCollectionFactory->create() as $region) {
            /** @var \Magento\Directory\Model\Region $region */
            $countryId                  = (string)$region->getCountryId();
            $regionId                   = (int)$region->getRegionId();
            $namesByRegionId[$regionId] = $region->getDefaultName();

            if (in_array($countryId, $nameBasedCountries, true)) {
                $nameToId[$countryId . '|' . $region->getDefaultName()] = $regionId;
            } elseif ($region->getCode() !== '') {
                // Magento's region "code" column is inconsistently formatted across
                // countries: some store the bare local code (US "CA", BE "VAN" - needs
                // the country prefix added to match ISO 3166-2), others already store
                // the full ISO code including the country prefix (PL "PL-24", LT "LT-VL").
                // Detect which case applies instead of always prepending.
                $code = strtoupper($region->getCode());
                $key  = str_starts_with($code, strtoupper($countryId) . '-') ? $code : strtoupper($countryId) . '-' . $code;
                $mapping[$key] = $regionId;
            }
        }

        foreach (self::ISO_SUBDIVISION_NAMES as $isoCode => $info) {
            $nameKey = $info['country'] . '|' . $info['name'];
            if (isset($nameToId[$nameKey])) {
                $mapping[$isoCode] = $nameToId[$nameKey];
            }
        }

        $this->subdivisionMapping = $mapping;
        $this->namesByRegionId    = $namesByRegionId;
        // array_flip: safe here because $mapping's values (region_id) are unique per
        // region - each region can appear at most once via the code branch and once
        // via the name-override branch, and those two never target the same country.
        $this->codesByRegionId = array_flip($mapping);
    }
}
