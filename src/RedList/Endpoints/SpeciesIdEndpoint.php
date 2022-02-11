<?php declare(strict_types=1);
/**
 * This file is part of marijnvanwezel/iucnbot.
 *
 * (c) Marijn van Wezel <marijnvanwezel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MarijnVanWezel\IUCNBot\RedList\Endpoints;

/**
 * Represents the individual species by ID endpoint for global assessments.
 *
 * @link https://apiv3.iucnredlist.org/api/v3/docs#species-individual-id
 */
class SpeciesIdEndpoint extends Endpoint
{
	/**
	 * @var int The ID of the species to get the global assessment for.
	 */
	private int $id;

	/**
	 * Set the ID of the species to get the global assessment for.
	 *
	 * @param int $id
	 * @return $this
	 */
	public function withID(int $id): static
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * @inheritDoc
	 */
	protected function getEndpoint(): string
	{
		return '/species/id/:id';
	}

	/**
	 * @inheritDoc
	 */
	protected function getParameters(): array
	{
		return [
			'id' => $this->id
		];
	}
}