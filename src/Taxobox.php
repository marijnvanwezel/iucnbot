<?php declare(strict_types=1);
/**
 * This file is part of marijnvanwezel/iucnbot.
 *
 * (c) Marijn van Wezel <marijnvanwezel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MarijnVanWezel\IUCNBot;

use MarijnVanWezel\IUCNBot\RedList\RedListAssessment;
use MarijnVanWezel\IUCNBot\RedList\RedListStatus;

final class Taxobox
{
	/**
	 * @var array
	 */
	private readonly array $taxoboxInfo;

	public function __construct(array $taxoboxInfo)
	{
		$this->taxoboxInfo = self::cleanTaxoboxInfo($taxoboxInfo);
	}

	/**
	 * Returns the RedList ID if it exists.
	 *
	 * @return int|null
	 */
	public function getRedListId(): ?int
	{
		if (!isset($this->taxoboxInfo['rl-id'])) {
			return null;
		}

		if (!ctype_digit($this->taxoboxInfo['rl-id'])) {
			// The RedList ID is invalid, treat it as non-existent
			return null;
		}

		return intval($this->taxoboxInfo['rl-id']);
	}

	/**
	 * Returns the RedList status if it exists.
	 *
	 * @return RedListStatus|null
	 */
	public function getRedListStatus(): ?RedListStatus
	{
		if (!isset($this->taxoboxInfo['status'])) {
			return null;
		}

		return RedListStatus::fromString($this->taxoboxInfo['status']);
	}

	/**
	 * Returns the scientific name if it exists.
	 *
	 * @return string|null
	 */
	public function getScientificName(): ?string
	{
		if (!isset($this->taxoboxInfo['w-naam'])) {
			return null;
		}

		return $this->taxoboxInfo['w-naam'];
	}

	/**
	 * Returns the name if it exists.
	 *
	 * @return string|null
	 */
	public function getName(): ?string
	{
		if (!isset($this->taxoboxInfo['naam'])) {
			return null;
		}

		return $this->taxoboxInfo['naam'];
	}

	/**
	 * Returns the year assessed if it exists.
	 *
	 * @return int|null
	 */
	public function getYearAssessed(): ?int
	{
		if (!isset($this->taxoboxInfo['statusbron'])) {
			return null;
		}

		if (!ctype_digit($this->taxoboxInfo['statusbron'])) {
			// The year is invalid, treat it as non-existent
			return null;
		}

		return intval($this->taxoboxInfo['statusbron']);
	}

	/**
	 * Returns true if and only if the taxobox is outdated.
	 *
	 * @param RedListAssessment $assessment The assessment to compare against
	 * @return bool True if it is outdated, false otherwise
	 */
	public function isOutdated(RedListAssessment $assessment): bool
	{
		if ($this->isExtinct()) {
			return false;
		}

		return $this->getRedListId() !== $assessment->taxonId ||
			$this->getRedListStatus() !== $assessment->status ||
			$this->getYearAssessed() !== $assessment->yearAssessed;
	}

	/**
	 * Returns true if the taxobox describes a species that is extinct.
	 *
	 * @return bool
	 */
	public function isExtinct(): bool
	{
		return $this->getRedListStatus() === RedListStatus::EX;
	}

	/**
	 * Cleans the given taxobox info.
	 *
	 * @param array $taxoboxInfo
	 * @return array
	 */
	private static function cleanTaxoboxInfo(array $taxoboxInfo): array
	{
		$cleanedTaxobox = [];

		foreach ($taxoboxInfo as $key => $value) {
			$cleanedTaxobox[is_string($key) ? trim($key) : $key] = self::cleanValue($value);
		}

		return $cleanedTaxobox;
	}

	/**
	 * Cleans the given value.
	 *
	 * @param string $value
	 * @return string
	 */
	private static function cleanValue(string $value): string
	{
		return trim(preg_replace('/{{.+?}}/', '', $value), "[]'\" \t\n\r\0\x0B");
	}
}