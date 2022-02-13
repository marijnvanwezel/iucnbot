<?php

namespace MarijnVanWezel\IUCNBot\Tests;

use MarijnVanWezel\IUCNBot\RedList\RedListAssessment;
use MarijnVanWezel\IUCNBot\RedList\RedListStatus;
use MarijnVanWezel\IUCNBot\Taxobox;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MarijnVanWezel\IUCNBot\Taxobox
 */
class TaxoboxTest extends TestCase
{
	/**
	 * @dataProvider provideGetRedListIdData
	 */
	public function testGetRedListId(?int $expected, Taxobox $taxobox): void
	{
		$this->assertSame($expected, $taxobox->getRedListId());
	}

	/**
	 * @dataProvider provideGetRedListStatusData
	 */
	public function testGetRedListStatus(?RedListStatus $expected, Taxobox $taxobox): void
	{
		$this->assertSame($expected, $taxobox->getRedListStatus());
	}

	/**
	 * @dataProvider provideGetScientificNameData
	 */
	public function testGetScientificName(?string $expected, Taxobox $taxobox): void
	{
		$this->assertSame($expected, $taxobox->getScientificName());
	}

	/**
	 * @dataProvider provideGetNameData
	 */
	public function testGetName(?string $expected, Taxobox $taxobox): void
	{
		$this->assertSame($expected, $taxobox->getName());
	}

	/**
	 * @dataProvider provideGetYearAssessedData
	 */
	public function testGetYearAssessed(?int $expected, Taxobox $taxobox): void
	{
		$this->assertSame($expected, $taxobox->getYearAssessed());
	}

	/**
	 * @dataProvider provideOutdatedData
	 */
	public function testOutdated(Taxobox $taxobox, RedListAssessment $assessment): void
	{
		$this->assertTrue($taxobox->isOutdated($assessment));
	}

	/**
	 * @dataProvider provideNotOutdatedData
	 */
	public function testNotOutdated(Taxobox $taxobox, RedListAssessment $assessment): void
	{
		$this->assertFalse($taxobox->isOutdated($assessment));
	}

	/**
	 * @dataProvider provideIsExtinctData
	 */
	public function testIsExtinct(bool $expected, Taxobox $taxobox): void
	{
		$this->assertSame($expected, $taxobox->isExtinct());
	}

	public function provideGetRedListIdData(): array
	{
		return [
			'empty array' => [null, new Taxobox([])],
			'missing key' => [null, new Taxobox(['status' => 'VU'])],
			'invalid' => [null, new Taxobox(['rl-id' => 'invalid'])],
			'simple' => [19, new Taxobox(['rl-id' => '19'])],
			'simple2' => [420, new Taxobox(['rl-id' => '420'])],
			'mangled key' => [19, new Taxobox(['    ' . PHP_EOL . ' rl-id ' => '19'])],
			'mangled value' => [19, new Taxobox(['rl-id' => '\'"[[19]]"" {{!}}'])],
		];
	}

	public function provideGetRedListStatusData(): array
	{
		return [
			'empty array' => [null, new Taxobox([])],
			'missing key' => [null, new Taxobox(['rl-id' => '1234'])],
			'simple' => [RedListStatus::VU, new Taxobox(['status' => 'VU'])],
			'simple2' => [RedListStatus::VU, new Taxobox(['status' => 'kwetsbaar'])],
			'mangled key' => [RedListStatus::VU, new Taxobox(['    ' . PHP_EOL . ' status ' => 'VU'])],
			'mangled value' => [RedListStatus::VU, new Taxobox(['status' => '\'"[[VU]]"" {{!}}'])],
		];
	}

	public function provideGetScientificNameData(): array
	{
		return [
			'empty array' => [null, new Taxobox([])],
			'missing key' => [null, new Taxobox(['rl-id' => '1234'])],
			'simple' => ['Raphus cucullatus', new Taxobox(['w-naam' => 'Raphus cucullatus'])],
			'simple2' => ['Ailuropoda melanoleuca', new Taxobox(['w-naam' => 'Ailuropoda melanoleuca'])],
			'mangled key' => ['Raphus cucullatus', new Taxobox(['    ' . PHP_EOL . ' w-naam ' => 'Raphus cucullatus'])],
			'mangled value' => ['Raphus cucullatus', new Taxobox(['w-naam' => '\'"[[Raphus cucullatus]]"" {{†}}'])],
		];
	}

	public function provideGetNameData(): array
	{
		return [
			'empty array' => [null, new Taxobox([])],
			'missing key' => [null, new Taxobox(['rl-id' => '1234'])],
			'simple' => ['Dodo', new Taxobox(['naam' => 'Dodo'])],
			'simple2' => ['Reuzenpanda', new Taxobox(['naam' => 'Reuzenpanda'])],
			'mangled key' => ['Dodo', new Taxobox(['    ' . PHP_EOL . ' naam ' => 'Dodo'])],
			'mangled value' => ['Dodo', new Taxobox(['naam' => '\'"[[Dodo]]"" {{†}}'])],
		];
	}

	public function provideGetYearAssessedData(): array
	{
		return [
			'empty array' => [null, new Taxobox([])],
			'missing key' => [null, new Taxobox(['status' => 'VU'])],
			'invalid' => [null, new Taxobox(['statusbron' => 'invalid'])],
			'simple' => [2020, new Taxobox(['statusbron' => '2020'])],
			'simple2' => [2021, new Taxobox(['statusbron' => '2021'])],
			'mangled key' => [2020, new Taxobox(['    ' . PHP_EOL . ' statusbron ' => '2020'])],
			'mangled value' => [2020, new Taxobox(['statusbron' => '\'"[[2020]]"" {{!}}'])],
		];
	}

	public function provideOutdatedData(): array
	{
		return [
			[new Taxobox([]), new RedListAssessment(1, RedListStatus::VU)],
			[new Taxobox([]), new RedListAssessment(1, RedListStatus::VU, 2022)],
			[new Taxobox(['rl-id' => '1']),  new RedListAssessment(1, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1']),  new RedListAssessment(1, RedListStatus::VU, 2022)],
			[new Taxobox(['rl-id' => '1', 'statusbron' => '2022']),  new RedListAssessment(1, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '2', 'status' => 'VU']),  new RedListAssessment(1, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'VU']),  new RedListAssessment(1, RedListStatus::VU, 2022)],
			[new Taxobox(['rl-id' => '1', 'status' => 'VU', 'statusbron' => '2022']),  new RedListAssessment(1, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'VU', 'statusbron' => '2021']),  new RedListAssessment(1, RedListStatus::VU, 2022)],
			[new Taxobox(['rl-id' => '1', 'statusbron' => '2022']),  new RedListAssessment(1, RedListStatus::VU)]
		];
	}

	public function provideNotOutdatedData(): array
	{
		return [
			[new Taxobox(['rl-id' => '1', 'status' => 'VU']), new RedListAssessment(1, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'kwetsbaar']), new RedListAssessment(1, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'KWETSBAAR']), new RedListAssessment(1, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'VU', 'statusbron' => '2022']), new RedListAssessment(1, RedListStatus::VU, 2022)],
			[new Taxobox(['rl-id' => '1', 'status' => 'EX']), new RedListAssessment(0, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'Extinct']), new RedListAssessment(0, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'Fossiel']), new RedListAssessment(0, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'fossiel']), new RedListAssessment(0, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'fossil']), new RedListAssessment(0, RedListStatus::VU)],
			[new Taxobox(['rl-id' => '1', 'status' => 'Fossil']), new RedListAssessment(0, RedListStatus::VU)],
		];
	}

	public function provideIsExtinctData()
	{
		return [
			[true, new Taxobox(['rl-id' => '1', 'status' => 'Extinct'])],
			[true, new Taxobox(['rl-id' => '1', 'status' => 'Fossiel'])],
			[true, new Taxobox(['rl-id' => '1', 'status' => 'fossiel'])],
			[true, new Taxobox(['rl-id' => '1', 'status' => 'fossil'])],
			[true, new Taxobox(['rl-id' => '1', 'status' => 'Fossil'])],
			[false, new Taxobox(['rl-id' => '1'])],
			[false, new Taxobox(['rl-id' => '1', 'status' => 'VU'])],
		];
	}
}