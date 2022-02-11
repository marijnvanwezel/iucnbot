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
 * Represents an endpoint of the IUCN Red List API.
 */
abstract class Endpoint
{
	final protected const API_ENDPOINT = 'https://apiv3.iucnredlist.org/api/v3';

	/**
	 * @param string $apiToken The API token with which to authenticate against the IUCN Red List API.
	 */
	private readonly string $apiToken;

	/**
	 * @param string $apiToken The API token with which to authenticate against the IUCN Red List API.
	 */
	public function __construct(string $apiToken)
	{
		$this->apiToken = $apiToken;
	}

	/**
	 * Calls the endpoint.
	 *
	 * @return array
	 */
	public function call(): array {
		$uri = $this->buildRequestURI();

		try {
			$curl = curl_init($uri);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$result = curl_exec($curl);
		} finallY {
			curl_close($curl);
		}

		return json_decode($result, true);
	}

	/**
	 * Returns the API endpoint. Any parameters can be specified using the ":%s" syntax. For example,
	 *
	 * "/citation/:name/region/:region_identifier"
	 *
	 * @return string
	 */
	abstract protected function getEndpoint(): string;

	/**
	 * Returns the parameters to use when calling the API.
	 *
	 * @return string[]
	 */
	abstract protected function getParameters(): array;

	/**
	 * Builds the request URI from the parameters and the endpoint.
	 *
	 * @return string
	 */
	private function buildRequestURI(): string
	{
		$uri = sprintf(static::API_ENDPOINT . '%s?token=%s', $this->getEndpoint(), $this->apiToken);

		foreach ($this->getParameters() as $name => $value) {
			$uri = str_replace(":$name", strval($value), $uri);
		}

		return $uri;
	}
}