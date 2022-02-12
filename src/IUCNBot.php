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

// Required for "escapeshellarg" to work with Unicode strings
setlocale(LC_CTYPE, "UTF8", "en_US.UTF-8");

use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Addwiki\Mediawiki\Api\Client\Auth\UserAndPassword;
use Addwiki\Mediawiki\Api\MediawikiFactory;
use Addwiki\Mediawiki\Api\Service\PageGetter;
use Addwiki\Mediawiki\Api\Service\RevisionSaver;
use Addwiki\Mediawiki\DataModel\Content;
use Addwiki\Mediawiki\DataModel\EditInfo;
use Addwiki\Mediawiki\DataModel\Page;
use Addwiki\Mediawiki\DataModel\Revision;
use Exception;
use JetBrains\PhpStorm\Pure;
use MarijnVanWezel\IUCNBot\MediaWiki\CategoryTraverser;
use MarijnVanWezel\IUCNBot\RedList\RedListAssessment;
use MarijnVanWezel\IUCNBot\RedList\RedListClient;
use MarijnVanWezel\IUCNBot\RedList\RedListStatus;

class IUCNBot
{
	// The category which to get the articles to consider from
	private const CATEGORY_PAGES = [
		'Categorie:Wikipedia:Diersoorten',
		'Categorie:Wikipedia:Plantenlemma'
	];
	private const MEDIAWIKI_ENDPOINT = 'https://nl.wikipedia.org/w/api.php'; // nlwiki

	private readonly RedListClient $redListClient;
	private readonly ActionApi $mediaWikiClient;
	private readonly PageGetter $pageGetter;
	private readonly RevisionSaver $revisionSaver;

	public function __construct(string $redListToken, string $mediaWikiUser, string $mediaWikiPassword)
	{
		$this->redListClient = new RedListClient($redListToken);
		$this->mediaWikiClient = new ActionApi(
			self::MEDIAWIKI_ENDPOINT,
			new UserAndPassword($mediaWikiUser, $mediaWikiPassword)
		);

		$mediaWikiFactory = new MediawikiFactory($this->mediaWikiClient);

		$this->pageGetter = $mediaWikiFactory->newPageGetter();
		$this->revisionSaver = $mediaWikiFactory->newRevisionSaver();
	}

	/**
	 * Run the bot.
	 *
	 * @return void
	 */
	public function run(): void
	{
		$categoryTraverser = new CategoryTraverser($this->mediaWikiClient, 500);

		foreach (self::CATEGORY_PAGES as $categoryPage) {
			echo "Traversing category \e[3m$categoryPage\e[0m ..." . PHP_EOL;

			foreach ($categoryTraverser->fetchPages($categoryPage) as $species) {
				try {
					echo "... Updating \e[3m$species\e[0m ... ";
					echo $this->handleSpecies($species) ? 'Done' : 'Skipped';
				} catch (Exception $exception) {
					echo "{$exception->getMessage()}";
				} finally {
					echo PHP_EOL;
				}

				sleep(10);
			}
		}

		echo "... Done." . PHP_EOL;
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
		$page = $this->pageGetter->getFromTitle($species);
		$pageContent = $page->getRevisions()->getLatest()?->getContent()->getData() ?? throw new Exception('Could not retrieve page content');
		$taxobox = $this->getTaxobox($pageContent);
		$assessment = $this->getAssessment($species, $taxobox);

		if (!$this->isTaxoboxOutdated($assessment, $taxobox) || !$this->isEditAllowed($pageContent)) {
			return false;
		}

		$newContent = $this->putTaxobox($pageContent, $assessment->toDutchTaxobox());

		if ($newContent === $pageContent) {
			return false;
		}

		$this->updatePage($page, $newContent);

		return true;
	}

	/**
	 * Retrieves the taxobox information from the given wikitext.
	 *
	 * @param string $wikitext
	 * @return array
	 * @throws Exception
	 */
	public static function getTaxobox(string $wikitext): array
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

		foreach ($taxobox as $key => $value) {
			$cleanTaxobox[trim($key)] = static::cleanParameter($value);
		}

		return $cleanTaxobox;
	}

	/**
	 * @param string $parameter
	 * @return string
	 */
	public static function cleanParameter(string $parameter): string
	{
		$parameter = preg_replace('/{{.+?}}/', '', $parameter);
		$parameter = trim($parameter);
		$parameter = trim($parameter, '\'"');

		return trim($parameter, '[]');
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

		if (empty($response['result'])) {
			throw new Exception('Not assessed');
		}

		$taxonId = $response['result'][0]['taxonid'] ?? throw new Exception('Missing "taxonid"');
		$category = $response['result'][0]['category'] ?? throw new Exception('Missing "category"');
		$publishedYear = $response['result'][0]['published_year'] ?? null;

		if (!is_int($taxonId)) {
			throw new Exception('Invalid "taxonid"');
		}

		if (!is_string($category)) {
			throw new Exception('Invalid "category"');
		}

		if ($publishedYear !== null && !is_int($publishedYear)) {
			throw new Exception('Invalid "published_year"');
		}

		return new RedListAssessment(
			$taxonId,
			RedListStatus::fromString($category),
			$publishedYear
		);
	}

	/**
	 * Returns true if and only if the taxobox is outdated.
	 *
	 * @param RedListAssessment $assessment The current RedList assessment
	 * @param array $taxobox The taxobox to evaluate
	 * @return bool
	 */
	#[Pure] public static function isTaxoboxOutdated(RedListAssessment $assessment, array $taxobox): bool
	{
		if (!isset($taxobox['rl-id']) || $taxobox['rl-id'] !== strval($assessment->taxonId)) {
			return true;
		}

		if (!isset($taxobox['status']) || $assessment->status->equalsDutch($taxobox['status'])) {
			return true;
		}

		$statusSource = $assessment->statusSource !== null ? strval($assessment->statusSource) : null;

		return ($taxobox['statusbron'] ?? null) !== $statusSource;
	}

	/**
	 * This page returns true if and only if the page is allowed to be edited.
	 *
	 * There are various reasons why a page should not be changed, such as when it contains a "nobots" template or
	 * when it is still being worked on.
	 *
	 * @param string $wikitext
	 * @return bool
	 */
	public static function isEditAllowed(string $wikitext): bool
	{
		$disallowRegex = [
			'/{{\s*nobots/i', // {{nobots}}
			'/{{\s*bots\s*\|\s*deny\s*=\s*all/i', // {{bots|deny=all}}
			'/{{\s*nuweg/i', // {{nuweg}}
			'/{{\s*speedy/i', // {{speedy}}
			'/{{\s*delete/i', // {{delete}}
			'/{{\s*meebezig/i', // {{meebezig}}
			'/{{\s*mee bezig/i', // {{mee bezig}}
			'/{{\s*wiu/i', // {{wiu}}
			'/{{\s*wiu2/i' // {{wiu2}}
		];

		foreach ($disallowRegex as $regex) {
			if (preg_match($regex, $wikitext)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Puts the given taxobox in the given wikitext.
	 *
	 * @note The parameters of the existing taxobox are not replaced with the given taxobox info. Parameters can only be
	 * added or altered. If a parameter does not exist in $taxoboxInfo, it will not be updated/removed on the page.
	 *
	 * @param string $wikitext
	 * @param array $taxoboxInfo
	 * @return string The resulting page
	 *
	 * @throws Exception
	 */
	public static function putTaxobox(string $wikitext, array $taxoboxInfo): string
	{
		$taxoboxInfo = json_encode($taxoboxInfo, JSON_THROW_ON_ERROR);
		$command = __DIR__ . '/mwparserfromhell/put_taxobox_info ' .
			escapeshellarg($wikitext) . ' ' . escapeshellarg($taxoboxInfo);

		exec($command, $shellOutput, $resultCode);

		if ($resultCode !== 0 || empty($shellOutput)) {
			throw new Exception('Could not update taxobox');
		}

		return implode("\n", $shellOutput);
	}

	/**
	 * Update the page with the given content.
	 *
	 * @param Page $page
	 * @param string $newContent
	 * @throws Exception
	 */
	private function updatePage(Page $page, string $newContent): void
	{
		if (empty(trim($newContent))) {
			// Sanity check
			throw new Exception('Tried to save empty page');
		}

		$content = new Content($newContent);
		$revision = new Revision($content, $page->getPageIdentifier());
		$editInfo = new EditInfo('Bijwerken/toevoegen status van de Rode Lijst van de IUCN', false, true);

		if (!$this->revisionSaver->save($revision, $editInfo)) {
			throw new Exception('Failed to save revision');
		}
	}
}
