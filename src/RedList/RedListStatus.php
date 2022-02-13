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
	 * @return RedListStatus|null The corresponding RedListStatus, or NULL if the status is not matched
	 */
	public static function fromString(string $status): ?RedListStatus
	{
		return match (strtolower($status)) {
			'ex', 'uitgestorven', 'extinct', 'fossil', 'fossiel' => RedListStatus::EX,
			'ew', 'uihw', 'uitgestorven in het wild', 'uitgestorveninhetwild', 'extinct in the wild', 'extinctinthewild' => RedListStatus::EW,
			'cr', 'kritiek', 'critically endangered' => RedListStatus::CR,
			'en', 'bedreigd', 'endangered' => RedListStatus::EN,
			'vu', 'kwetsbaar', 'vulnerable' => RedListStatus::VU,
			'cd', 'lr/cd', 'vba', 'van bescherming afhankelijk', 'vanbeschermingafhankelijk', 'conservation dependent' => RedListStatus::CD,
			'nt', 'lr/nt', 'gevoelig', 'near threatened' => RedListStatus::NT,
			'lc', 'lr/lc', 'veilig', 'secure', 'niet bedreigd', 'least concern' => RedListStatus::LC,
			'dd', 'onzeker', 'datadeficient', 'data deficient' => RedListStatus::DD,
			default => null
		};
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
		return sprintf('Categorie:IUCN-status %s', match ($this) {
			RedListStatus::EX => 'uitgestorven',
			RedListStatus::EW => 'uitgestorven in het wild',
			RedListStatus::CR => 'kritiek',
			RedListStatus::EN => 'bedreigd',
			RedListStatus::VU => 'kwetsbaar',
			RedListStatus::CD => 'van bescherming afhankelijk',
			RedListStatus::NT => 'gevoelig',
			RedListStatus::LC => 'niet bedreigd',
			RedListStatus::DD => 'onzeker'
		});
	}
}