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

use MarijnVanWezel\IUCNBot\RedList\RedListStatus;

/**
 * Data-class containing information about the assessment of a species.
 */
final class RedListAssessment
{
	public function __construct(
		public readonly int $taxonId,
		public readonly RedListStatus $status,
		public readonly ?int $statusSource = null
	) {}

	/**
	 * Converts the assessment to a taxobox info array for the Dutch Wikipedia.
	 *
	 * @link https://nl.wikipedia.org/wiki/Sjabloon:Taxobox
	 * @return array
	 */
	public function toDutchTaxobox(): array
	{
		return [
			'rl-id' => $this->taxonId,
			'status' => $this->status->toString(),
			'statusbron' => $this->statusSource ?? ''
		];
	}
}