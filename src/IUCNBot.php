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

ini_set('user_agent', 'IUCNBot/1.0 (https://github.com/marijnvanwezel/iucnbot)');

use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Addwiki\Mediawiki\Api\Client\Auth\UserAndPassword;
use Addwiki\Mediawiki\Api\MediawikiFactory;
use Addwiki\Mediawiki\Api\Service\PageGetter;
use Addwiki\Mediawiki\Api\Service\RevisionSaver;
use DateTime;
use Error;
use Exception;
use MarijnVanWezel\IUCNBot\RedList\RedListAssessment;
use MarijnVanWezel\IUCNBot\RedList\RedListClient;
use MarijnVanWezel\IUCNBot\RedList\RedListStatus;

class IUCNBot
{
	// The category which to get the articles to consider from
	private const CATEGORY_PAGES = [
		'Categorie:Wikipedia:IUCN Red List Status CD',
		'Categorie:Wikipedia:IUCN Red List Status CR',
		'Categorie:Wikipedia:IUCN Red List Status DD',
		'Categorie:Wikipedia:IUCN Red List Status EN',
		'Categorie:Wikipedia:IUCN Red List Status EW',
		'Categorie:Wikipedia:IUCN Red List Status EX',
		'Categorie:Wikipedia:IUCN Red List Status LC',
		'Categorie:Wikipedia:IUCN Red List Status NA',
		'Categorie:Wikipedia:IUCN Red List Status NT',
		'Categorie:Wikipedia:IUCN Red List Status VU',
		'Categorie:Wikipedia:Diersoorten',
		'Categorie:Wikipedia:Plantenlemma'
	];
	private const MEDIAWIKI_ENDPOINT = 'https://nl.wikipedia.org/w/api.php'; // nlwiki

	private readonly RedListClient $redListClient;
	private readonly ActionApi $mediaWikiClient;
	private readonly PageGetter $pageGetter;
	private readonly RevisionSaver $revisionSaver;
	private readonly bool $dryRun;
	private readonly string $mediaWikiUser;
	private readonly AssessedPages $assessedPages;

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
		$this->mediaWikiUser = $mediaWikiUser;

		$this->assessedPages = new AssessedPages();
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
				if ($this->assessedPages->isAssessed($species)) {
					// Skip the pages we have already checked in a previous run
					continue;
				}

				$hasEdited = false;

				try {
					echo "... Updating \e[3m$species\e[0m ... ";

					$hasEdited = $this->handleSpecies($species);

					echo $hasEdited ? "\e[32mDone\e[0m" . PHP_EOL : "\e[33mSkipped\e[0m" . PHP_EOL;
				} catch (Exception|Error $exception) {
					echo "\e[31m{$exception->getMessage()}\e[0m" . PHP_EOL;
				}

				if (!$this->dryRun) {
					$this->assessedPages->markAssessed($species);
				}

				$hasEdited ? sleep(4) : sleep(2);
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
		if ($this->hasTalkpageEdits(5)) {
			die("\e[31mTalkpage was edited.\e[0m" . PHP_EOL);
		}

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
	 * Returns true if the bots talkpage was edited in the last $minutesAgo minutes.
	 *
	 * @param int $minutesAgo
	 * @return bool
	 * @throws Exception
	 */
	private function hasTalkpageEdits(int $minutesAgo): bool
	{
		$query = sprintf('%s?%s', self::MEDIAWIKI_ENDPOINT, http_build_query([
			'action' => 'query',
			'format' => 'json',
			'prop' => 'revisions',
			'titles' => sprintf('User talk:%s', $this->mediaWikiUser),
			'rvprop' => 'timestamp',
			'rvlimit' => 1
		]));

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $query);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$apiResponse = curl_exec($curl);

		curl_close($curl);

		if (!is_string($apiResponse)) {
			throw new Exception('Could not get talkpage edits');
		}

		$apiResponse = json_decode($apiResponse, true);

		if (!is_array($apiResponse)) {
			throw new Exception('Could not get talkpage edits');
		}

		$pages = $apiResponse['query']['pages'];
		$talkPage = $pages[array_key_first($pages)];
		$timestamp = $talkPage['revisions'][0]['timestamp'];

		return time() - strtotime($timestamp) < $minutesAgo * 60;
	}
}
