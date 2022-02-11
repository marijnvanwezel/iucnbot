<?php declare(strict_types=1);
/**
 * This file is part of marijnvanwezel/iucnbot.
 *
 * (c) Marijn van Wezel <marijnvanwezel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MarijnVanWezel\IUCNBot\MediaWiki;

use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Generator;

/**
 * Lazily traverses a category.
 */
class CategoryTraverser
{
	private readonly ActionApi $apiClient;
	private readonly int $batchSize;

	/**
	 * @param ActionApi $apiClient The ActionApi client to use.
	 * @param int $batchSize The size of each request.
	 */
	public function __construct(ActionApi $apiClient, int $batchSize = 500)
	{
		$this->apiClient = $apiClient;
		$this->batchSize = $batchSize;
	}

	/**
	 * Lazily fetches all pages in the given category.
	 *
	 * @param string $category
	 * @return Generator
	 */
	public function fetchPages(string $category): Generator
	{
		$params = [
			'list' => 'categorymembers',
			'cmlimit' => $this->batchSize,
			'cmtype' => 'page', // Only return pages, and not subcategories
			'cmnamespace' => 0, // Only search in the 'main' namespace
			'cmtitle' => $category
		];

		$result = [];

		do {
			// Set up continue parameter if it's been set already.
			if (isset($result['continue']['cmcontinue'])) {
				$params['cmcontinue'] = $result['continue']['cmcontinue'];
			}

			// Run the actual query.
			$result = $this->apiClient->request(ActionRequest::simpleGet('query', $params));

			if (!array_key_exists('query', $result)) {
				return;
			}

			// Yield the results
			foreach ($result['query']['categorymembers'] as $member) {
				if (!array_key_exists('pageid', $member)) {
					continue;
				}

				yield $member['title'];
			}
		} while (isset($result['continue']));
	}
}