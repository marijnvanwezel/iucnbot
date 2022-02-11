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

ini_set("xdebug.var_display_max_children", '-1');
ini_set("xdebug.var_display_max_data", '-1');
ini_set("xdebug.var_display_max_depth", '-1');
setlocale(LC_CTYPE, "UTF8", "en_US.UTF-8");

use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Addwiki\Mediawiki\Api\Client\Auth\UserAndPassword;
use Addwiki\Mediawiki\Api\MediawikiFactory;
use Exception;
use MarijnVanWezel\IUCNBot\RedList\RedListAssessment;
use MarijnVanWezel\IUCNBot\RedList\RedListClient;
use MarijnVanWezel\IUCNBot\RedList\RedListStatus;

class IUCNBot
{
	// The category which to get the articles to consider from
	private const CATEGORY_PAGES = ['Categorie:Wikipedia:Diersoorten', 'Categorie:Wikipedia:Plantenlemma']; // nlwiki species
	private const MEDIAWIKI_ENDPOINT = 'https://nl.wikipedia.org/w/api.php'; // nlwiki

	private readonly RedListClient $redListClient;
	private readonly ActionApi $mediaWikiClient;
	private readonly MediawikiFactory $mediaWikiFactory;

	public function __construct(
		string $redListToken,
		string $mediaWikiUser,
		string $mediaWikiPassword
	) {
		$this->redListClient = new RedListClient($redListToken);
		$this->mediaWikiClient = new ActionApi(
			self::MEDIAWIKI_ENDPOINT,
			new UserAndPassword($mediaWikiUser, $mediaWikiPassword)
		);
		$this->mediaWikiFactory = new MediawikiFactory($this->mediaWikiClient);
	}

	/**
	 * Run the bot.
	 *
	 * @return void
	 */
	public function run(): void
	{
		$categoryTraverser = new MediaWiki\CategoryTraverser($this->mediaWikiClient, 500);

		foreach (self::CATEGORY_PAGES as $categoryPage) {
			echo "Traversing category \e[3m$categoryPage\e[0m ..." . PHP_EOL;

			foreach ($categoryTraverser->fetchPages($categoryPage) as $species) {
				try {
					echo "... updating \e[3m$species\e[0m ... ";
					echo $this->handleSpecies($species) ? 'done' : 'skipped';
				} catch (Exception $exception) {
					echo "failed: {$exception->getMessage()}";
				} finally {
					echo PHP_EOL;
				}

				// Rate-limit the bot to 4 edits per minute by sleeping for 15 seconds
				sleep(15);
			}
		}

		echo "... done." . PHP_EOL;
    }

	/**
	 * Handle update of a single species.
	 *
	 * @param string $species The species to add/update the status of
	 * @return bool True if the page was updated, false otherwise
	 * @throws Exception If something went wrong :)
	 */
	private function handleSpecies(string $species): bool
	{
		$pageGetter = $this->mediaWikiFactory->newPageGetter();
		$page = $pageGetter->getFromTitle($species);
		$oldPageContent = $page->getRevisions()->getLatest()?->getContent()->getData();

		if ($oldPageContent === null) {
			throw new Exception('Invalid page content');
		}

		$taxobox = $this->getTaxobox($oldPageContent);
		$assessment = $this->getAssessment($species, $taxobox);

		if (!$this->updateNeeded($assessment, $taxobox)) {
			return false;
		}

		$newPageContent = $this->putTaxobox($oldPageContent, $assessment->toDutchTaxobox());

		// TODO: Make the edit

		return true;
	}

	/**
	 * Queries the RedList API and parses the result.
	 *
	 * @param string $species
	 * @param array $taxoboxInfo
	 * @return RedListAssessment
	 * @throws Exception
	 */
	private function getAssessment(string $species, array $taxoboxInfo): RedListAssessment
	{
		if (isset($taxoboxInfo['rl-id']) && ctype_digit($taxoboxInfo['rl-id'])) {
			// The taxobox already contains a RedList ID
			$response = $this->redListClient->speciesId->withID(intval($taxoboxInfo['rl-id']))->call();
		} elseif (isset($taxoboxInfo['w-naam'])) {
			// The taxobox has a "w-naam" (scientific name)
			$response = $this->redListClient->species->withName($taxoboxInfo['w-naam'])->call();
		} elseif (isset($taxoboxInfo['naam'])) {
			// The taxobox has a "naam" (name)
			$response = $this->redListClient->species->withName($taxoboxInfo['naam'])->call();
		} else {
			// Use the page name
			$response = $this->redListClient->species->withName($species)->call();
		}

		$result = $response['result'] ?? throw new Exception('Missing "result" key.');

		if (empty($result)) {
			throw new Exception('Not assessed');
		}

		$taxonId = $result[0]['taxonid'] ?? throw new Exception('Missing "taxonid"');
		$category = $result[0]['category'] ?? throw new Exception('Missing "category"');
		$publishedYear = $result[0]['published_year'] ?? null;

		if (!is_int($taxonId)) {
			throw new Exception('Invalid "taxonid"');
		}

		if (!is_string($category)) {
			throw new Exception('Invalid "category"');
		}

		if ($publishedYear !== null && !is_int($publishedYear)) {
			throw new Exception('Invalid "published_year"');
		}

		$status = RedListStatus::fromString($category);

		return new RedListAssessment(
			$taxonId,
			$status,
			$publishedYear
		);
	}

	/**
	 * Returns true if and only if the taxobox needs updating.
	 *
	 * @param RedListAssessment $assessment
	 * @param array $taxobox
	 * @return bool
	 */
	private function updateNeeded(RedListAssessment $assessment, array $taxobox): bool
	{
		if (!isset($taxobox['rl-id']) || $taxobox['rl-id'] !== strval($assessment->taxonId)) {
			return true;
		}

		if (!isset($taxobox['status']) || $assessment->status->equalsDutch($taxobox['status'])) {
			return true;
		}

		$statusSource = $assessment->statusSource !== null ?
			strval($assessment->statusSource) : null;

		return ($taxobox['statusbron'] ?? null) !== $statusSource;
	}

	/**
	 * Parses the given page and returns the taxobox info.
	 *
	 * @param string $wikitext
	 * @return array
	 * @throws Exception
	 */
	private function getTaxobox(string $wikitext): array
	{
		$command = __DIR__ . '/mwparserfromhell/get_taxobox_info ' . escapeshellarg($wikitext);

		exec($command, $shellOutput, $resultCode);

		if ($resultCode !== 0 || empty($shellOutput) || !isset($shellOutput[0])) {
			throw new Exception('Could not parse taxobox');
		}

		$taxobox = json_decode(implode("\n", $shellOutput), true);

		if ($taxobox === null) {
			throw new Exception('Could not parse taxobox');
		}

		$cleanTaxobox = [];

		foreach ( $taxobox as $key => $value ) {
			$cleanTaxobox[trim($key)] = static::cleanParameter($value);
		}

		return $cleanTaxobox;
	}

	/**
	 * Parses the given page content and updates the taxobox with the given taxobox information.
	 *
	 * @note The parameters of the existing taxobox are not replaced with the given taxobox info. Parameters can only be
	 * added or altered. If a parameter does not exist in $taxoboxInfo, it will not be updated/removed on the page.
	 *
	 * @param string $pageContent
	 * @param array $taxoboxInfo
	 * @return string The resulting page
	 *
	 * @throws Exception
	 */
	private function putTaxobox(string $pageContent, array $taxoboxInfo): string
	{
		$taxoboxInfo = json_encode($taxoboxInfo, JSON_THROW_ON_ERROR);
		$command = __DIR__ . '/mwparserfromhell/put_taxobox_info ' . escapeshellarg($pageContent) . ' ' . escapeshellarg($taxoboxInfo);

		exec($command, $shellOutput, $resultCode);

		if ($resultCode !== 0 || empty($shellOutput)) {
			throw new Exception('Could not update taxobox');
		}

		return implode("\n", $shellOutput);
	}

	/**
	 * @param string $parameter
	 * @return string
	 */
	private static function cleanParameter(string $parameter): string
	{
		// Remove any templates
		$parameter = preg_replace('/{{.+?}}/', '', $parameter);

		// Remove any whitespace
		$parameter = trim($parameter);

		// Remove any apostrophes
		$parameter = trim($parameter, '\'"');

		// Remove any link syntax
		return trim($parameter, '[]');
	}
}
