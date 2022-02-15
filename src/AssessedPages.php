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

/**
 * Hashmap of all pages we have assessed in a previous run.
 */
class AssessedPages
{
	private const CHECKED_PAGES_FILE = '.page_cache';
	private array $assessedPagesHashmap;

	public function __construct()
	{
		$this->assessedPagesHashmap = self::getAssessedPagesHashmap();
	}

	/**
	 * Returns true if and only if $pageName has already been assessed.
	 *
	 * @param string $pageName
	 * @return bool
	 */
	public function isAssessed(string $pageName): bool
	{
		return isset($this->assessedPagesHashmap[$pageName]);
	}

	/**
	 * Mark the given page as assessed.
	 *
	 * @param string $pageName
	 * @return void
	 */
	public function markAssessed(string $pageName): void
	{
		$file = fopen(self::CHECKED_PAGES_FILE, 'a');

		fwrite($file, "\n" . $pageName);
		fclose($file);

		$this->assessedPagesHashmap[$pageName] = true;
	}

	/**
	 * Returns a hashmap of all assessed pages, where the key is the page that is has been assessed.
	 *
	 * @return array
	 */
	private static function getAssessedPagesHashmap(): array
	{
		if (!file_exists(self::CHECKED_PAGES_FILE)) {
			file_put_contents(self::CHECKED_PAGES_FILE, '');
		}

		$checkedPages = explode("\n", file_get_contents(self::CHECKED_PAGES_FILE));

		// Create hashmap using array_flip
		return array_flip($checkedPages);
	}
}