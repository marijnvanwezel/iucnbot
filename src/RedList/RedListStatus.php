<?php declare(strict_types=1);
/**
 * This file is part of marijnvanwezel/iucnbot.
 *
 * (c) Marijn van Wezel <marijnvanwezel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MarijnVanWezel\IUCNBot\RedList;

use Exception;
use JetBrains\PhpStorm\Pure;

enum RedListStatus
{
	private const DICT_DUTCH = [
		'EX' => ['ex', 'uitgestorven', 'extinct'],
		'EW' => ['ew', 'uihw', 'uitgestorven in het wild', 'uitgestorveninhetwild', 'extinct in the wild', 'extinctinthewild'],
		'CR' => ['cr', 'kritiek', 'critically endangered'],
		'EN' => ['en', 'bedreigd', 'endangered'],
		'VU' => ['vu', 'kwetsbaar', 'vulnerable'],
		'CD' => ['cd', 'lr/cd', 'vba', 'van bescherming afhankelijk', 'vanbeschermingafhankelijk', 'conservation dependent'],
		'NT' => ['nt', 'lr/nt', 'gevoelig', 'near threatened'],
		'LC' => ['lc', 'lr/lc', 'veilig', 'secure', 'niet bedreigd', 'least concern'],
		'DD' => ['dd', 'onzeker', 'datadeficient', 'data deficient']
	];

	case EX; // Extinct
	case EW; // Extinct in the wild
	case CR; // Critically endangered
	case EN; // Endangered
	case VU; // Vulnerable
	case CD; // Conservation dependent
	case NT; // Near threatened
	case LC; // Least concern
	case DD; // Data deficient

	/**
	 * Parses the given status.
	 *
	 * @param string $status
	 * @return RedListStatus
	 * @throws Exception When the status is invalid
	 */
	public static function fromString(string $status): RedListStatus
	{
		return match ($status) {
			'EX' => RedListStatus::EX,
			'EW' => RedListStatus::EW,
			'CR' => RedListStatus::CR,
			'EN' => RedListStatus::EN,
			'VU' => RedListStatus::VU,
			'CD', 'LR/cd' => RedListStatus::CD,
			'NT', 'LR/nt' => RedListStatus::NT,
			'LC', 'LR/lc' => RedListStatus::LC,
			'DD' => RedListStatus::DD,
			default => throw new Exception('Invalid status')
		};
	}

	/**
	 * Returns true if and only if the given status is equal to this status (Dutch-oriented comparison).
	 *
	 * This function was derived from the allowed values for the "status" parameter in the Taxobox template on the
	 * Dutch Wikipedia.
	 *
	 * @link https://nl.wikipedia.org/wiki/Sjabloon:Taxobox
	 *
	 * @param string $status
	 * @return bool
	 */
	#[Pure] public function equalsDutch(string $status): bool
	{
		return $this->equals($status, self::DICT_DUTCH);
	}

	/**
	 * Returns true if and only if the given status is equal to this status according to the given dictionary.
	 *
	 * @param string $status
	 * @param array $dict
	 * @return bool
	 */
	#[Pure] public function equals(string $status, array $dict): bool
	{
		$status = mb_strtolower($status);

		foreach ($dict as $key => $translations) {
			if (in_array($status, $translations, true)) {
				return $this->toString() === $key;
			}
		}

		return false;
	}

	/**
	 * Returns the name of this status.
	 *
	 * @return string
	 */
	#[Pure] public function toString(): string
	{
		return match ($this) {
			RedListStatus::EX => 'EX',
			RedListStatus::EW => 'EW',
			RedListStatus::CR => 'CR',
			RedListStatus::EN => 'EN',
			RedListStatus::VU => 'VU',
			RedListStatus::CD => 'CD',
			RedListStatus::NT => 'NT',
			RedListStatus::LC => 'LC',
			RedListStatus::DD => 'DD'
		};
	}
}