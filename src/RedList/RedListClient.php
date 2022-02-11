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

use MarijnVanWezel\IUCNBot\RedList\Endpoints\SpeciesEndpoint;
use MarijnVanWezel\IUCNBot\RedList\Endpoints\SpeciesIdEndpoint;

/**
 * Tiny wrapper around the IUCN Red List API.
 *
 * @property SpeciesEndpoint $species
 * @property SpeciesIdEndpoint $speciesId
 */
class RedListClient extends BaseRedListClient
{
	public function __get(string $name)
	{
		return $this->endpointFactory->getEndpoint($name, $this->apiToken);
	}
}