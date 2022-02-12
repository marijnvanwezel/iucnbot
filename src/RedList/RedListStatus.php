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
	#[Pure] public function equals(string $status): bool
	{
		$status = mb_strtolower($status);

		foreach (self::DICT_DUTCH as $key => $translations) {
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

	/**
	 * Returns the category for this status (Dutch Wikipedia).
	 *
	 * @return string
	 */
	public function toCategory(): string
	{
		return match ($this) {
			RedListStatus::EX => 'Categorie:IUCN-status uitgestorven',
			RedListStatus::EW => 'Categorie:IUCN-status uitgestorven in het wild',
			RedListStatus::CR => 'Categorie:IUCN-status kritiek',
			RedListStatus::EN => 'Categorie:IUCN-status bedreigd',
			RedListStatus::VU => 'Categorie:IUCN-status kwetsbaar',
			RedListStatus::CD => 'Categorie:IUCN-status van bescherming afhankelijk',
			RedListStatus::NT => 'Categorie:IUCN-status gevoelig',
			RedListStatus::LC => 'Categorie:IUCN-status niet bedreigd',
			RedListStatus::DD => 'Categorie:IUCN-status onzeker'
		};
	}
}