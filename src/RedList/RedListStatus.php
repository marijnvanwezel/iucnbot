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

enum RedListStatus
{
	case EX;
	case EW;
	case CR;
	case EN;
	case VU;
	case LR;
	case NT;
	case LC;
	case DD;

	/**
	 * Parses the given status.
	 *
	 * @param string $status
	 * @return RedListStatus
	 * @throws Exception When the status is invalid
	 */
	public static function parse(string $status): RedListStatus
	{
		return match($status) {
			'EX' => RedListStatus::EX,
			'EW' => RedListStatus::EW,
			'CR' => RedListStatus::CR,
			'EN' => RedListStatus::EN,
			'VU' => RedListStatus::VU,
			'LR' => RedListStatus::LR,
			'NT' => RedListStatus::NT,
			'LC' => RedListStatus::LC,
			'DD' => RedListStatus::DD,
			default => throw new Exception('Invalid status')
		};
	}

	/**
	 * Returns the name of this status.
	 *
	 * @return string
	 */
	public function toString(): string
	{
		return match($this) {
			RedListStatus::EX => 'EX',
			RedListStatus::EW => 'EW',
			RedListStatus::CR => 'CR',
			RedListStatus::EN => 'EN',
			RedListStatus::VU => 'VU',
			RedListStatus::LR => 'LR',
			RedListStatus::NT => 'NT',
			RedListStatus::LC => 'LC',
			RedListStatus::DD => 'DD'
		};
	}
}