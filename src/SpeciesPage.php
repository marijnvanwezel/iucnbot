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

use Addwiki\Mediawiki\Api\Service\RevisionSaver;
use Addwiki\Mediawiki\DataModel\Content;
use Addwiki\Mediawiki\DataModel\EditInfo;
use Addwiki\Mediawiki\DataModel\PageIdentifier;
use Addwiki\Mediawiki\DataModel\Revision;
use Exception;
use MarijnVanWezel\IUCNBot\RedList\RedListAssessment;
use MarijnVanWezel\IUCNBot\RedList\RedListStatus;

final class SpeciesPage
{
	private const CATEGORY_REGEX = '/\[\[\s*Categor(ie|y):IUCN-status (bedreigd|van bescherming afhankelijk|gevoelig|kritiek|kwetsbaar|niet bedreigd|niet geëvalueerd|onzeker|uitgestorven|uitgestorven in het wild)\s*\]\]\s*\n?/i';
	private const SUMMARY = 'Bijwerken/toevoegen status van de Rode Lijst van de IUCN';

	private readonly PageIdentifier $identifier;
	private string $content;

	/**
	 * @param PageIdentifier $identifier
	 * @param string $content
	 */
	public function __construct(PageIdentifier $identifier, string $content)
	{
		$this->identifier = $identifier;
		$this->content = $content;
	}

	/**
	 * Returns the page content.
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		return $this->content;
	}

	/**
	 * Returns the page identifier.
	 *
	 * @return PageIdentifier
	 */
	public function getIdentifier(): PageIdentifier
	{
		return $this->identifier;
	}

	/**
	 * Returns the taxobox on this page.
	 *
	 * @throws Exception
	 */
	public function getTaxobox(): Taxobox
	{
		$command = __DIR__ . '/mwparserfromhell/get_taxobox_info ' . escapeshellarg($this->content);

		exec($command, $shellOutput, $resultCode);

		if ($resultCode !== 0 || empty($shellOutput) || !isset($shellOutput[0])) {
			throw new Exception('Could not parse taxobox');
		}

		$taxoboxInfo = json_decode(implode("\n", $shellOutput), true);

		if (!is_array($taxoboxInfo)) {
			throw new Exception('Could not parse taxobox');
		}

		return new Taxobox($taxoboxInfo);
	}

	/**
	 * Returns true if and only if the page is allowed to be edited.
	 *
	 * There are various reasons why a page should not be changed, such as when it contains a "nobots" template or
	 * when it is still being worked on.
	 *
	 * @return bool
	 */
	public function isEditAllowed(): bool
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
			if (preg_match($regex, $this->content) === 1) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Updates the page with the given assessment.
	 *
	 * @param RedListAssessment $assessment
	 * @return void
	 * @throws Exception
	 */
	public function updateAssessment(RedListAssessment $assessment): void
	{
		$this->updateTaxobox($assessment);
		$this->updateCategories($assessment->status);
	}

	/**
	 * Stores the page using the given RevisionSaver.
	 *
	 * @throws Exception
	 */
	public function storePage(RevisionSaver $saver): void
	{
		if (empty(trim($this->getContent()))) {
			// Sanity check
			throw new Exception('Tried to save empty page');
		}

		$revision = new Revision(new Content($this->getContent()), $this->getIdentifier());
		$editInfo = new EditInfo(self::SUMMARY, false, true);

		if (!$saver->save($revision, $editInfo)) {
			throw new Exception('Failed to save revision');
		}
	}

	/**
	 * Updates the taxobox with the given assessment.
	 *
	 * @param RedListAssessment $assessment
	 * @throws Exception
	 */
	private function updateTaxobox(RedListAssessment $assessment): void
	{
		$command = __DIR__ . '/mwparserfromhell/put_taxobox_info ' .
			escapeshellarg($this->content) . ' ' .
			escapeshellarg(json_encode($assessment->toArray(), JSON_THROW_ON_ERROR));

		exec($command, $shellOutput, $resultCode);

		if ($resultCode !== 0 || empty($shellOutput)) {
			throw new Exception('Could not update taxobox');
		}

		$newPageContent = implode("\n", $shellOutput);

		if (preg_match('/{{\s*taxobox/i', mb_strtolower($newPageContent)) !== 1) {
			// Sanity check: Make sure the returned text actually still contains a Taxobox template
			throw new Exception('Could not update taxobox');
		}

		$this->content = $newPageContent;
	}

	/**
	 * Updates the categories on the page if necessary.
	 *
	 * @param RedListStatus $status
	 * @return void
	 */
	private function updateCategories(RedListStatus $status): void
	{
		$pageContent = preg_replace(self::CATEGORY_REGEX, '', $this->content);
		$category = sprintf('[[%s]]', $status->toCategory());

		$this->content = rtrim($pageContent, "\n") . "\n" . $category;
	}
}