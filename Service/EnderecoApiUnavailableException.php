<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Service;

/**
 * Signals that the Endereco API itself is unreachable or rejecting requests
 * (invalid API key, connection failure) - as opposed to a problem with a single
 * order's address data. Distinguished from a per-order failure so callers can
 * stop retrying it for every remaining order in a batch: the underlying cause
 * (a wrong key, an unreachable host) won't resolve itself mid-run, so repeating
 * the same failing request once per order only wastes calls and floods the log
 * with one near-identical error per order instead of a single, actionable one.
 */
class EnderecoApiUnavailableException extends \RuntimeException
{
}
