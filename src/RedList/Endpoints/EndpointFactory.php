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

use InvalidArgumentException;

class EndpointFactory
{
	private const ENDPOINTS = [
		'species' => SpeciesEndpoint::class,
		'speciesId' => SpeciesIdEndpoint::class
	];

	/**
	 * Retrieve the endpoint with the given name.
	 *
	 * @param string $endpoint
	 * @param string $apiToken
	 * @return Endpoint
	 */
	public function getEndpoint(string $endpoint, string $apiToken): Endpoint
	{
		$endpoint = self::ENDPOINTS[$endpoint] ??
			throw new InvalidArgumentException('Invalid endpoint.');

		return new $endpoint($apiToken);
	}
}