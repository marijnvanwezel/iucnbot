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
 * Represents the individual species endpoint for global assessments.
 *
 * @link https://apiv3.iucnredlist.org/api/v3/docs#species-individual-name
 */
class SpeciesEndpoint extends Endpoint
{
	/**
	 * @var string The name of the species to get the global assessment for.
	 */
	private string $name;

	/**
	 * Set the name of the species to get the global assessment for.
	 *
	 * @param string $name
	 * @return $this
	 */
	public function withName(string $name): static
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * @inheritDoc
	 */
	protected function getEndpoint(): string
	{
		return '/species/:name';
	}

	/**
	 * @inheritDoc
	 */
	protected function getParameters(): array
	{
		return [
			'name' => $this->name
		];
	}
}