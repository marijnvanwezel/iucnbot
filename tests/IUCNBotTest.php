<?php declare(strict_types=1);
/**
 * This file is part of marijnvanwezel/iucnbot.
 *
 * (c) Marijn van Wezel <marijnvanwezel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MarijnVanWezel\IUCNBot\Tests;

use Exception;
use MarijnVanWezel\IUCNBot\IUCNBot;
use MarijnVanWezel\IUCNBot\RedList\RedListAssessment;
use MarijnVanWezel\IUCNBot\RedList\RedListStatus;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MarijnVanWezel\IUCNBot\IUCNBot
 */
class IUCNBotTest extends TestCase
{
	/**
	 * @dataProvider provideGetTaxoboxData
	 */
	public function testGetTaxobox(string $wikitext, array $expected): void
	{
		$this->assertSame($expected, IUCNBot::getTaxobox($wikitext));
	}

	/**
	 * @dataProvider provideGetTaxoboxInvalidData
	 */
	public function testGetTaxoboxInvalid(string $wikitext): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Could not parse taxobox');

		IUCNBot::getTaxobox($wikitext);
	}

	/**
	 * @dataProvider provideCleanParameterData
	 */
	public function testCleanParameter(string $parameter, string $expected): void
	{
		$this->assertSame($expected, IUCNBot::cleanParameter($parameter));
	}

	/**
	 * @dataProvider provideIsTaxoboxOutdatedData
	 */
	public function testIsTaxoboxOutdated(RedListAssessment $assessment, array $taxobox, bool $expected): void
	{
		$this->assertSame($expected, IUCNBot::isTaxoboxOutdated($assessment, $taxobox));
	}

	/**
	 * @dataProvider provideIsEditAllowedData
	 */
	public function testIsEditAllowed(string $pageContent, bool $expected): void
	{
		$this->assertSame($expected, IUCNBot::isEditAllowed($pageContent));
	}

	/**
	 * @dataProvider providePutTaxoboxData
	 */
	public function testPutTaxobox(string $wikitext, array $taxoboxInfo, string $expected): void
	{
		$this->assertSame($expected, IUCNBot::putTaxobox($wikitext, $taxoboxInfo));
	}

	/**
	 * @dataProvider providePutTaxoboxInvalidData
	 */
	public function testPutTaxoboxInvalid(string $wikitext): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Could not update taxobox');

		IUCNBot::putTaxobox($wikitext, []);
	}

	public function provideGetTaxoboxData(): array
	{
		return [
			['{{Taxobox}}', []],
			['{{taxobox}}', []],
			['{{Taxobox vogel}}', []],
			['{{Taxobox vogel|soort=dier}}', ['soort' => 'dier']],
			[<<<WIKITEXT
			{{Taxobox vogel|
			soort = dier
			|rl-id=   19298
			|   w-naam = ''[[Dodo]]'' {{†}}
			}}
			WIKITEXT, ['soort' => 'dier', 'rl-id' => '19298', 'w-naam' => 'Dodo']],
			[<<<WIKITEXT
			{{Taxobox vogel|
			soort = dier
			|
			rl-id=   19298
			|   w-naam     = '''[[Dodo]]''' {{†}} {{Uitgestorven}}|vogel
			}}
			WIKITEXT, ['soort' => 'dier', 'rl-id' => '19298', 'w-naam' => 'Dodo', 1 => 'vogel']],
			[<<<WIKITEXT
			{{Sjabloon:Taxobox vogel|soort=dier}}
			WIKITEXT, ['soort' => 'dier']],
			[<<<WIKITEXT
			{{ Template:Taxobox vogel|soort=dier
			}}
			WIKITEXT, ['soort' => 'dier']],
		];
	}

	public function provideGetTaxoboxInvalidData(): array
	{
		return [
			['{{Ttaxobox}}'],
			['{{Taxoboxx}}'],
			['{{Taxobox amfibie'],
			['{{Infobox vis|foo=bar}}']
		];
	}

	public function provideCleanParameterData(): array
	{
		return [
			["''[[Example]]'' {{†}}", 'Example'],
			['Example', 'Example'],
			["''[[Example]]''", 'Example'],
			["''Hokus pokus''", 'Hokus pokus'],
			["'''Hokus pokus'' {{Pilatus}}", 'Hokus pokus'],
			["  'Hokus Pokus'  ", 'Hokus Pokus']
		];
	}

	public function provideIsTaxoboxOutdatedData(): array
	{
		return [
			[new RedListAssessment(1, RedListStatus::EX), [
				'rl-id' => 1,
				'status' => 'ex'
			], false],
			[new RedListAssessment(1, RedListStatus::EX), [
				'rl-id' => 1,
				'status' => 'UITGESTORVEN'
			], false],
			[new RedListAssessment(1, RedListStatus::EX), [
				'rl-id' => 1,
				'status' => 'Uitgestorven'
			], false],
			[new RedListAssessment(1, RedListStatus::EX), [
				'rl-id' => 1,
				'status' => 'Extinct'
			], false],
			[new RedListAssessment(1, RedListStatus::EW), [
				'rl-id' => 1,
				'status' => 'EW'
			], false],
			[new RedListAssessment(1, RedListStatus::EW), [
				'rl-id' => 1,
				'status' => 'UIHW'
			], false],
			[new RedListAssessment(1, RedListStatus::EW), [
				'rl-id' => 1,
				'status' => 'ew'
			], false],
			[new RedListAssessment(1, RedListStatus::EW), [
				'rl-id' => 1,
				'status' => 'uihw'
			], false],
			[new RedListAssessment(1, RedListStatus::EW), [
				'rl-id' => 1,
				'status' => 'Uitgestorven in het Wild'
			], false],
			[new RedListAssessment(1, RedListStatus::EW), [
				'rl-id' => 1,
				'status' => 'Uitgestorveninhetwild'
			], false],
			[new RedListAssessment(1, RedListStatus::EW), [
				'rl-id' => 1,
				'status' => 'uitgestorveninhetwild'
			], false],
			[new RedListAssessment(1, RedListStatus::EW), [
				'rl-id' => 1,
				'status' => 'Extinct in the Wild'
			], false],
			[new RedListAssessment(1, RedListStatus::EW), [
				'rl-id' => 1,
				'status' => 'extinctinthewild'
			], false],
			[new RedListAssessment(1, RedListStatus::CR), [
				'rl-id' => 1,
				'status' => 'CR'
			], false],
			[new RedListAssessment(1, RedListStatus::CR), [
				'rl-id' => 1,
				'status' => 'cr'
			], false],
			[new RedListAssessment(1, RedListStatus::CR), [
				'rl-id' => 1,
				'status' => 'kritiek'
			], false],
			[new RedListAssessment(1, RedListStatus::CR), [
				'rl-id' => 1,
				'status' => 'KRITIEK'
			], false],
			[new RedListAssessment(1, RedListStatus::CR), [
				'rl-id' => 1,
				'status' => 'Critically endangered'
			], false],
			[new RedListAssessment(1, RedListStatus::CR), [
				'rl-id' => 1,
				'status' => 'CRITICALLY ENDANGERED'
			], false],
			[new RedListAssessment(1, RedListStatus::EN), [
				'rl-id' => 1,
				'status' => 'EN'
			], false],
			[new RedListAssessment(1, RedListStatus::EN), [
				'rl-id' => 1,
				'status' => 'en'
			], false],
			[new RedListAssessment(1, RedListStatus::EN), [
				'rl-id' => 1,
				'status' => 'BEDREIGD'
			], false],
			[new RedListAssessment(1, RedListStatus::EN), [
				'rl-id' => 1,
				'status' => 'Bedreigd'
			], false],
			[new RedListAssessment(1, RedListStatus::EN), [
				'rl-id' => 1,
				'status' => 'bedreigd'
			], false],
			[new RedListAssessment(1, RedListStatus::EN), [
				'rl-id' => 1,
				'status' => 'endangered'
			], false],
			[new RedListAssessment(1, RedListStatus::EN), [
				'rl-id' => 1,
				'status' => 'Endangered'
			], false],
			[new RedListAssessment(1, RedListStatus::EN), [
				'rl-id' => 1,
				'status' => 'ENDANGERED'
			], false],
			[new RedListAssessment(1, RedListStatus::VU), [
				'rl-id' => 1,
				'status' => 'VU'
			], false],
			[new RedListAssessment(1, RedListStatus::VU), [
				'rl-id' => 1,
				'status' => 'Vulnerable'
			], false],
			[new RedListAssessment(1, RedListStatus::VU), [
				'rl-id' => 1,
				'status' => 'KWETSBAAR'
			], false],
			[new RedListAssessment(1, RedListStatus::VU), [
				'rl-id' => '1',
				'status' => 'Kwetsbaar'
			], false],
			[new RedListAssessment(1, RedListStatus::VU), [
				'rl-id' => '1',
				'status' => 'kwetsbaar'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'CD'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'LR/CD'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'lr/cd'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'cd'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'vba'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'VBA'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'Van Bescherming Afhankelijk'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'vanbeschermingafhankelijk'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'Conservation dependent'
			], false],
			[new RedListAssessment(1, RedListStatus::CD), [
				'rl-id' => '1',
				'status' => 'Vanbeschermingafhankelijk'
			], false],
			[new RedListAssessment(1, RedListStatus::NT), [
				'rl-id' => '1',
				'status' => 'NT'
			], false],
			[new RedListAssessment(1, RedListStatus::NT), [
				'rl-id' => '1',
				'status' => 'nt'
			], false],
			[new RedListAssessment(1, RedListStatus::NT), [
				'rl-id' => '1',
				'status' => 'lr/nt'
			], false],
			[new RedListAssessment(1, RedListStatus::NT), [
				'rl-id' => '1',
				'status' => 'LR/nt'
			], false],
			[new RedListAssessment(1, RedListStatus::NT), [
				'rl-id' => '1',
				'status' => 'LR/NT'
			], false],
			[new RedListAssessment(1, RedListStatus::NT), [
				'rl-id' => '1',
				'status' => 'Gevoelig'
			], false],
			[new RedListAssessment(1, RedListStatus::NT), [
				'rl-id' => '1',
				'status' => 'gevoelig'
			], false],
			[new RedListAssessment(1, RedListStatus::NT), [
				'rl-id' => '1',
				'status' => 'near threatened'
			], false],
			[new RedListAssessment(1, RedListStatus::NT), [
				'rl-id' => '1',
				'status' => 'Near Threatened'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'LC'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'lc'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'LR/lc'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'LR/LC'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'veilig'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'Veilig'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'Secure'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'secure'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'Niet bedreigd'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'least concern'
			], false],
			[new RedListAssessment(1, RedListStatus::LC), [
				'rl-id' => '1',
				'status' => 'LEAST CONCERN'
			], false],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => '1',
				'status' => 'DD'
			], false],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => '1',
				'status' => 'Onzeker'
			], false],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => '1',
				'status' => 'onzeker'
			], false],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => '1',
				'status' => 'dd'
			], false],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => '1',
				'status' => 'datadeficient'
			], false],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => '1',
				'status' => 'DataDeficient'
			], false],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => '1',
				'status' => 'Data Deficient'
			], false],
			[new RedListAssessment(10, RedListStatus::DD), [
				'rl-id' => '10',
				'status' => 'Data Deficient'
			], false],
			[new RedListAssessment(9999999999, RedListStatus::DD), [
				'rl-id' => '9999999999',
				'status' => 'Data Deficient'
			], false],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => '1'
			], true],
			[new RedListAssessment(1, RedListStatus::DD), [
				'status' => 'Data Deficient'
			], true],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => 1,
				'status' => 'Data Deficient',
				'statusbron' => '2022'
			], true],
			[new RedListAssessment(1, RedListStatus::DD), [
				'status' => 'Data Deficient',
				'statusbron' => '2022'
			], true],
			[new RedListAssessment(1, RedListStatus::DD, 2022), [
				'rl-id' => 1,
				'status' => 'Data Deficient',
				'statusbron' => '2022'
			], false],
			[new RedListAssessment(1, RedListStatus::DD, 2022), [
				'rl-id' => 1,
				'status' => 'Data Deficient',
				'statusbron' => 2022
			], false],
			[new RedListAssessment(1, RedListStatus::VU, 2022), [
				'rl-id' => 1,
				'status' => 'VU'
			], true],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => 1,
				'status' => 'Data Deficient',
				'statusbron' => '2022'
			], true],
			[new RedListAssessment(1, RedListStatus::DD), [
				'rl-id' => 1,
				'status' => 'Data Deficient',
				'statusbron' => 2022
			], true],
			[new RedListAssessment(1, RedListStatus::DD, 2022), [
				'rl-id' => 1,
				'status' => 'Data Deficient',
				'statusbron' => '2019'
			], true],
			[new RedListAssessment(1, RedListStatus::DD, 2022), [
				'rl-id' => 1,
				'status' => 'Data Deficient',
				'statusbron' => 2019
			], true],
			[new RedListAssessment(1, RedListStatus::DD, 2022), [
				'rl-id' => 1,
				'status' => 'Data Deficient',
				'statusbron' => 2022,
				'something-else' => 2029
			], false],
			[new RedListAssessment(1, RedListStatus::DD, 2022), [
				'rl-id' => 1,
				'status' => 'Data Deficient',
				'statusbron' => 2019,
				'something-else' => 2029
			], true]
		];
	}

	public function provideIsEditAllowedData(): array
	{
		return [
			['Hello World', true],
			['{{Meebezig}}', false],
			['{{Meebezig}} De "Dodo" is een dode vogel', false],
			['{{meebezig}}, De "dodo" is een vogel', false],
			['{{ meebezig }}', false],
			['{{ bots | deny = all}}', false],
			[<<<WIKITEXT
			{{
			bots
			|deny = all}}
			WIKITEXT, false],
			['{{nuweg|}}', false],
			['{{NUWEG}}', false],
			['{{ nuweg }}', false],
			['{{ wiu }}', false],
			['{{ wiu2}}', false],
			['{{}}', true]
		];
	}

	public function providePutTaxoboxData(): array
	{
		return [
			[<<<WIKITEXT
			{{Taxobox
			}}
			WIKITEXT, ['rl-id' => 2022], <<<WIKITEXT
			{{Taxobox
			|rl-id=2022}}
			WIKITEXT],
			[<<<WIKITEXT
			{{Taxobox
			|soort=dier
			}}
			WIKITEXT, ['rl-id' => 2022], <<<WIKITEXT
			{{Taxobox
			|soort=dier
			|rl-id=2022
			}}
			WIKITEXT],
			[<<<WIKITEXT
			{{Taxobox
			| soort = dier
			}}
			WIKITEXT, ['rl-id' => 2022], <<<WIKITEXT
			{{Taxobox
			| soort = dier
			| rl-id = 2022
			}}
			WIKITEXT],
			[<<<WIKITEXT
			{{Taxobox
			| soort =dier
			| rl-id =2022
			}}
			WIKITEXT, ['rl-id' => 2022], <<<WIKITEXT
			{{Taxobox
			| soort =dier
			| rl-id =2022
			}}
			WIKITEXT],
			[<<<WIKITEXT
			{{Taxobox dier
			| soort =dier
			| rl-id =2022
			}}
			WIKITEXT, ['rl-id' => 2022], <<<WIKITEXT
			{{Taxobox dier
			| soort =dier
			| rl-id =2022
			}}
			WIKITEXT],
			[<<<WIKITEXT
			{{Taxobox
			| soort =dier
			| rl-id =2022
			}}
			
			Een dier is een dier.
			WIKITEXT, ['rl-id' => 2022], <<<WIKITEXT
			{{Taxobox
			| soort =dier
			| rl-id =2022
			}}
			
			Een dier is een dier.
			WIKITEXT],
			[<<<WIKITEXT
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = KWETSBAAR
			}}
			
			Een dier is een dier.
			WIKITEXT, ['rl-id' => 2022, 'status' => 'VU'], <<<WIKITEXT
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = VU
			}}
			
			Een dier is een dier.
			WIKITEXT],
		];
	}

	public function providePutTaxoboxInvalidData(): array
	{
		return [
			[<<<WIKITEXT
			{{Taxobo
			| soort =dier
			| rl-id =2022
			
			| status = KWETSBAAR
			}}
			
			Een dier is een dier.
			WIKITEXT],
			[<<<WIKITEXT
			{{Taxoboxx
			| soort =dier
			| rl-id =2022
			
			| status = KWETSBAAR
			}}
			
			Een dier is een dier.
			WIKITEXT],
			[<<<WIKITEXT
			{{}}
			WIKITEXT],
			[<<<WIKITEXT
			
			WIKITEXT],
		];
	}
}