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
use Addwiki\Mediawiki\DataModel\PageIdentifier;
use Addwiki\Mediawiki\DataModel\Revision;
use DateTime;
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
	private const SUMMARY = 'Bijwerken/toevoegen status van de Rode Lijst van de IUCN';

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
		$categoryTraverser = new CategoryTraverser($this->mediaWikiClient, 500);

		foreach (self::CATEGORY_PAGES as $categoryPage) {
			echo "Traversing category \e[3m$categoryPage\e[0m ..." . PHP_EOL;

			foreach ($categoryTraverser->fetchPages($categoryPage) as $species) {
				$pageUpdated = false;

				try {
					echo "... Updating \e[3m$species\e[0m ... ";

					$pageUpdated = $this->handleSpecies($species);

					echo $pageUpdated ? "\e[32mDone\e[0m" : "\e[33mSkipped\e[0m";
				} catch (Exception $exception) {
					echo "\e[31m{$exception->getMessage()}\e[0m";
				} finally {
					echo PHP_EOL;
				}

				// Always have some delay between calls
				$pageUpdated ? sleep(10) : sleep(5);
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

		if (!$this->isEditAllowed($pageContent)) {
			return false;
		}

		$taxobox = $this->getTaxobox($pageContent);

		if ($this->isExtinct($taxobox)) {
			return false;
		}

		$assessment = $this->getAssessment($species, $taxobox);

		if (!$this->isTaxoboxOutdated($assessment, $taxobox)) {
			return false;
		}

		$this->storeNewContent(
			$page->getPageIdentifier(),
			$this->updatePageWithAssessment($pageContent, $assessment)
		);

		return true;
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
			if (preg_match($regex, $wikitext) === 1) {
				return false;
			}
		}

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

		if (!is_array($taxobox)) {
			throw new Exception('Could not parse taxobox');
		}

		$cleanTaxobox = [];

		foreach ($taxobox as $key => $value) {
			if (!is_int($key)) {
				$key = trim($key);
			}

			$cleanTaxobox[$key] = static::cleanParameter($value);
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
	 * Returns true if the given taxobox describes a species that is extinct.
	 *
	 * @param array $taxobox
	 * @return bool
	 */
	#[Pure] public static function isExtinct(array $taxobox): bool
	{
		if (!isset($taxobox['status'])) {
			return false;
		}

		/** @note The status "fossil" is not an official RedList status and is therefore treated as a special case */

		return RedListStatus::EX->equals($taxobox['status']) ||
			in_array(mb_strtolower($taxobox['status']), ['fossiel', 'fossil']);
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
	 * Returns true if and only if the taxobox is outdated.
	 *
	 * @param RedListAssessment $assessment The current RedList assessment
	 * @param array $taxobox The taxobox to evaluate
	 * @return bool
	 */
	#[Pure] public static function isTaxoboxOutdated(RedListAssessment $assessment, array $taxobox): bool
	{
		if (!isset($taxobox['rl-id']) || strval($taxobox['rl-id']) !== strval($assessment->taxonId)) {
			return true;
		}

		if (!isset($taxobox['status']) || !$assessment->status->equals($taxobox['status'])) {
			return true;
		}

		$assessmentStatusSource = $assessment->yearAssessed !== null ? strval($assessment->yearAssessed) : null;
		$taxoboxStatusSource = isset($taxobox['statusbron']) ? strval($taxobox['statusbron']) : null;

		return $assessmentStatusSource !== $taxoboxStatusSource;
	}

	/**
	 * Update the page with the given content.
	 *
	 * @param PageIdentifier $pageIdentifier
	 * @param string $newContent
	 * @throws Exception
	 */
	private function storeNewContent(PageIdentifier $pageIdentifier, string $newContent): void
	{
		if (empty(trim($newContent))) {
			// Sanity check
			throw new Exception('Tried to save empty page');
		}

		$revision = new Revision(new Content($newContent), $pageIdentifier);
		$editInfo = new EditInfo(self::SUMMARY, false, true);

		if ($this->dryRun) {
			// If this is a dry-run, don't actually save the page
			return;
		}

		$success = $this->revisionSaver->save($revision, $editInfo);

		if (!$success) {
			throw new Exception('Failed to save revision');
		}
	}

	/**
	 * Updates the given wikitext with the given assessment.
	 *
	 * @param mixed $pageContent
	 * @param RedListAssessment $assessment
	 * @return string
	 * @throws Exception
	 */
	public static function updatePageWithAssessment(string $pageContent, RedListAssessment $assessment): string
	{
		$pageContent = static::putTaxobox($pageContent, $assessment);
		return static::updateCategory($pageContent, $assessment->status);
	}

	/**
	 * Puts the given taxobox in the given wikitext.
	 *
	 * @note The parameters of the existing taxobox are not replaced with the given taxobox info. Parameters can only be
	 * added or altered. If a parameter does not exist in $taxoboxInfo, it will not be updated/removed on the page.
	 *
	 * @param string $wikitext
	 * @param RedListAssessment $assessment
	 * @return string The resulting page
	 *
	 * @throws Exception
	 */
	public static function putTaxobox(string $wikitext, RedListAssessment $assessment): string
	{
		$command = __DIR__ . '/mwparserfromhell/put_taxobox_info ' .
			escapeshellarg($wikitext) . ' ' .
			escapeshellarg(json_encode($assessment->toTaxobox(), JSON_THROW_ON_ERROR));

		exec($command, $shellOutput, $resultCode);

		if ($resultCode !== 0 || empty($shellOutput)) {
			throw new Exception('Could not update taxobox');
		}

		$newWikitext = implode("\n", $shellOutput);

		if (preg_match('/{{\s*taxobox/i', mb_strtolower($newWikitext)) !== 1) {
			// Sanity check: Make sure the returned text actually still contains a Taxobox template
			throw new Exception('Could not update taxobox');
		}

		return $newWikitext;
	}

	/**
	 * Updates the categories on the page if necessary.
	 *
	 * @param string $wikitext
	 * @param RedListStatus $status
	 * @return string
	 */
	public static function updateCategory(string $wikitext, RedListStatus $status): string
	{
		// This regex matches inclusions of existing categories
		$regex = '/\[\[\s*Categor(ie|y):IUCN-status (bedreigd|van bescherming afhankelijk|gevoelig|kritiek|kwetsbaar|niet bedreigd|niet geëvalueerd|onzeker|uitgestorven|uitgestorven in het wild)\s*\]\]\s*\n?/i';
		$wikitext = preg_replace($regex, '', $wikitext);

		$newCategory = sprintf('[[%s]]', $status->toCategory());

		return rtrim($wikitext, "\n") . "\n" . $newCategory;
	}
}
