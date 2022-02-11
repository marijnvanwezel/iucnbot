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

use MarijnVanWezel\IUCNBot\RedList\Endpoints\EndpointFactory;

abstract class BaseRedListClient
{
	protected string $apiToken;
	protected EndpointFactory $endpointFactory;

	final public function __construct(string $apiToken)
	{
		$this->apiToken = $apiToken;
		$this->endpointFactory = new EndpointFactory();
	}

	final public function setToken(string $apiToken): static
	{
		$this->apiToken = $apiToken;

		return $this;
	}

	final public function setEndpointFactory(EndpointFactory $endpointFactory): static
	{
		$this->endpointFactory = $endpointFactory;

		return $this;
	}
}