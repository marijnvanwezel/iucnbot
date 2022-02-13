<?php

namespace MarijnVanWezel\IUCNBot\Tests;

use Addwiki\Mediawiki\DataModel\PageIdentifier;
use MarijnVanWezel\IUCNBot\RedList\RedListAssessment;
use MarijnVanWezel\IUCNBot\RedList\RedListStatus;
use MarijnVanWezel\IUCNBot\SpeciesPage;
use MarijnVanWezel\IUCNBot\Taxobox;
use PHPUnit\Framework\TestCase;

class SpeciesPageTest extends TestCase
{
	/**
	 * @dataProvider provideValidTaxoboxData
	 */
	public function testValidTaxobox(string $wikitext, Taxobox $expected): void
	{
		$speciesPage = new SpeciesPage(new PageIdentifier(), $wikitext);

		$this->assertEquals($expected, $speciesPage->getTaxobox());
	}

	/**
	 * @dataProvider provideIsEditAllowedData
	 */
	public function testIsEditAllowed(string $wikitext, bool $expected): void
	{
		$speciesPage = new SpeciesPage(new PageIdentifier(), $wikitext);

		$this->assertSame($expected, $speciesPage->isEditAllowed());
	}

	/**
	 * @dataProvider provideUpdateAssessmentData
	 */
	public function testPutTaxobox(string $wikitext, RedListAssessment $assessment, string $expected): void
	{
		$speciesPage = new SpeciesPage(new PageIdentifier(), $wikitext);
		$speciesPage->updateAssessment($assessment);

		$this->assertSame($expected, $speciesPage->getContent());
	}

	public function provideValidTaxoboxData()
	{
		return [
			['{{Taxobox}}', new Taxobox([])],
			['{{taxobox}}', new Taxobox([])],
			['{{Taxobox vogel}}', new Taxobox([])],
			['{{Taxobox vogel|soort=dier}}', new Taxobox(['soort' => 'dier'])],
			[<<<'WIKITEXT'
			{{Taxobox vogel|
			soort = dier
			|rl-id=   19298
			|   w-naam = ''[[Dodo]]'' {{†}}
			}}
			WIKITEXT, new Taxobox(['soort' => ' dier', 'rl-id' => '   19298', 'w-naam' => " ''[[Dodo]]'' {{†}}"])],
			[<<<'WIKITEXT'
			{{Taxobox vogel|
			soort = dier
			|
			rl-id=   19298
			|   w-naam     = '''[[Dodo]]''' {{†}} {{Uitgestorven}}|vogel
			}}
			WIKITEXT, new Taxobox(['soort' => 'dier', 'rl-id' => '19298', 'w-naam' => 'Dodo', 1 => 'vogel'])],
			[<<<'WIKITEXT'
			{{Sjabloon:Taxobox vogel|soort=dier}}
			WIKITEXT, new Taxobox(['soort' => 'dier'])],
			[<<<'WIKITEXT'
			{{ Template:Taxobox vogel|soort=dier
			}}
			WIKITEXT, new Taxobox(['soort' => 'dier'])],
			[<<<'WIKITEXT'
			{{Template:Taxobox vogel
			|soort=dier
			}}
			{{Template:Taxobox vogel
			|soort=insect
			}}
			WIKITEXT, new Taxobox(['soort' => 'dier'])],
			[<<<'WIKITEXT'
			{{Template:Taxobox vogel
			|soort=dier
			}}
			
			Some wikitext
			WIKITEXT, new Taxobox(['soort' => 'dier'])],
			[<<<'WIKITEXT'
			{{Template:Taxobox vogel
			|parameter={{Nested template|foo=bar}}
			|soort=dier
			}}
			WIKITEXT, new Taxobox(['parameter' => '{{Nested template|foo=bar}}', 'soort' => 'dier'])],
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
			[<<<'WIKITEXT'
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

	public function provideUpdateAssessmentData(): array
	{
		return [
			[<<<'WIKITEXT'
			{{Taxobox
			}}
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			|rl-id=2022|status=VU|statusbron=}}
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox
			|soort=dier
			}}
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			|soort=dier
			|rl-id=2022
			|status=VU
			|statusbron=
			}}
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox
			| soort = dier
			}}
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			| soort = dier
			| rl-id = 2022
			| status = VU
			| statusbron =
			}}
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			}}
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			| status =VU
			| statusbron =
			}}
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox dier
			| soort =dier
			| rl-id =2022
			}}
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox dier
			| soort =dier
			| rl-id =2022
			| status =VU
			| statusbron =
			}}
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			}}
			
			Een dier is een dier.
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			| status =VU
			| statusbron =
			}}
			
			Een dier is een dier.
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = KWETSBAAR
			}}
			
			Een dier is een dier.
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = VU
			| statusbron =
			}}
			
			Een dier is een dier.
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = KWETSBAAR
			}}
			
			Een dier is een dier.
			
			[[Categorie:IUCN-status niet bedreigd]]
			[[Categorie:Test]]
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = VU
			| statusbron =
			}}
			
			Een dier is een dier.
			
			[[Categorie:Test]]
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = KWETSBAAR
			}}
			
			Een dier is een dier.
			
			[[Categorie:Test]]
			[[Categorie:IUCN-status niet bedreigd]]
			[[Categorie:Test]]
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = VU
			| statusbron =
			}}
			
			Een dier is een dier.
			
			[[Categorie:Test]]
			[[Categorie:Test]]
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = KWETSBAAR
			}}
			
			Een dier is een dier.
			
			[[Categorie:Test]]
			[[Categorie:IUCN-status kwetsbaar]]
			[[Categorie:Test]]
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = VU
			| statusbron =
			}}
			
			Een dier is een dier.
			
			[[Categorie:Test]]
			[[Categorie:Test]]
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[<<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = KWETSBAAR
			}}
			
			Een dier is een dier.
			
			[[Categorie:Test]]
			[[Categorie:IUCN-status bedreigd]]
			[[Categorie:IUCN-status kwetsbaar]]
			[[Categorie:IUCN-status niet bedreigd]]
			[[Categorie:Test]]
			WIKITEXT, new RedListAssessment(2022, RedListStatus::VU), <<<'WIKITEXT'
			{{Taxobox
			| soort =dier
			| rl-id =2022
			
			| status = VU
			| statusbron =
			}}
			
			Een dier is een dier.
			
			[[Categorie:Test]]
			[[Categorie:Test]]
			[[Categorie:IUCN-status kwetsbaar]]
			WIKITEXT],
			[
				file_get_contents(__DIR__ . '/dodo.txt'),
				new RedListAssessment(420, RedListStatus::LC, 2022),
				file_get_contents(__DIR__ . '/dodo_new_assessment.txt')
			]
		];
	}
}