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

use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Addwiki\Mediawiki\Api\Client\Auth\UserAndPassword;
use Addwiki\Mediawiki\Api\MediawikiFactory;
use Addwiki\Mediawiki\Api\Service\PageGetter;
use Addwiki\Mediawiki\Api\Service\RevisionSaver;
use DateTime;
use Exception;
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
	private const CHECKED_PAGES_FILE = 'checked_pages.txt';

	private readonly RedListClient $redListClient;
	private readonly ActionApi $mediaWikiClient;
	private readonly PageGetter $pageGetter;
	private readonly RevisionSaver $revisionSaver;
	private readonly bool $dryRun;

	public function __construct(string $redListToken, string $mediaWikiUser, string $mediaWikiPassword, bool $dryRun = false)
	{
		$this->redListClient = new RedListClient($redListToken);
		$this->mediaWikiClient = new ActionApi(
			self::MEDIAWIKI_ENDPOINT,
			new UserAndPassword($mediaWikiUser, $mediaWikiPassword)
		);

		$mediaWikiFactory = new MediawikiFactory($this->mediaWikiClient);

		$this->pageGetter = $mediaWikiFactory->newPageGetter();
		$this->revisionSaver = $mediaWikiFactory->newRevisionSaver();
		$this->dryRun = $dryRun;
	}

	/**
	 * Run the bot.
	 *
	 * @return void
	 */
	public function run(): void
	{
		$checkedPages = $this->getCheckedPages();
		$categoryTraverser = new CategoryTraverser($this->mediaWikiClient, 500);

		foreach (self::CATEGORY_PAGES as $categoryPage) {
			echo "Traversing category \e[3m$categoryPage\e[0m ..." . PHP_EOL;

			foreach ($categoryTraverser->fetchPages($categoryPage) as $species) {
				if (in_array($species, $checkedPages, true)) {
					// Skip all the pages we have already checked in a previous run
					continue;
				}

				try {
					echo "... Updating \e[3m$species\e[0m ... ";
					echo $this->handleSpecies($species) ? "\e[32mDone\e[0m" : "\e[33mSkipped\e[0m";
					echo PHP_EOL;
				} catch (Exception $exception) {
					echo "\e[31m{$exception->getMessage()}\e[0m" . PHP_EOL;
				}

				if (!$this->dryRun) {
					$this->markPageAsChecked($species);
				}

				sleep(5);
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
		$pageContent = $page->getRevisions()->getLatest()?->getContent()->getData() ??
			throw new Exception('Could not retrieve page content');

		$speciesPage = new SpeciesPage($page->getPageIdentifier(), $pageContent);

		if (!$speciesPage->isEditAllowed()) {
			return false;
		}

		$taxobox = $speciesPage->getTaxobox();
		$assessment = $this->getAssessment($species, $taxobox);

		if (!$taxobox->isOutdated($assessment)) {
			return false;
		}

		$speciesPage->updateAssessment($assessment);

		if (!$this->dryRun) {
			$speciesPage->storePage($this->revisionSaver);
		}

		return true;
	}

	/**
	 * Queries the RedList API and parses the result.
	 *
	 * @param string $species
	 * @param Taxobox $taxobox
	 * @return RedListAssessment
	 * @throws Exception
	 */
	private function getAssessment(string $species, Taxobox $taxobox): RedListAssessment
	{
		// TODO: Refactor this function
		if ($taxobox->getRedListId() !== null) {
			$response = $this->redListClient->speciesId->withID($taxobox->getRedListId())->call();
		} elseif ($taxobox->getScientificName() !== null) {
			$response = $this->redListClient->species->withName($taxobox->getScientificName())->call();
		} elseif ($taxobox->getName() !== null) {
			$response = $this->redListClient->species->withName($taxobox->getName())->call();
		} else {
			$response = $this->redListClient->species->withName($species)->call();
		}

		if (empty($response['result'])) {
			throw new Exception('Not assessed');
		}

		$taxonId = $response['result'][0]['taxonid'] ?? throw new Exception('Missing "taxonid"');
		$category = $response['result'][0]['category'] ?? throw new Exception('Missing "category"');
		$assessmentDate = $response['result'][0]['assessment_date'] ?? null;

		if (!is_int($taxonId)) {
			throw new Exception('Invalid "taxonid"');
		}

		if (!is_string($category)) {
			throw new Exception('Invalid "category"');
		}

		if ($assessmentDate !== null && !is_string($assessmentDate)) {
			throw new Exception('Invalid "assessment_date"');
		}

		if ($assessmentDate !== null) {
			$dateTime = DateTime::createFromFormat('Y-m-d', $assessmentDate);
			$yearAssessed = intval($dateTime->format('Y'));

			if ($yearAssessed < 1948 || $yearAssessed > intval(date("Y"))) {
				throw new Exception('Invalid "assessment_date"');
			}
		} else {
			$yearAssessed = null;
		}

		return new RedListAssessment(
			$taxonId,
			RedListStatus::fromString($category),
			$yearAssessed
		);
	}

	/**
	 * Returns the list of pages previously checked.
	 *
	 * @return array
	 */
	private static function getCheckedPages(): array
	{
		if (!file_exists(self::CHECKED_PAGES_FILE)) {
			file_put_contents(self::CHECKED_PAGES_FILE, '');
		}

		return explode("\n", file_get_contents(self::CHECKED_PAGES_FILE));
	}

	/**
	 * Adds the page to the list of previously checked pages.
	 *
	 * @param string $pageName
	 * @return void
	 */
	private static function markPageAsChecked(string $pageName): void
	{
		$file = fopen(self::CHECKED_PAGES_FILE, 'a');

		fwrite($file, "\n" . $pageName);
		fclose($file);
	}
}
