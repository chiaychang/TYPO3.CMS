<?php
namespace TYPO3\CMS\Backend\Tests\Unit\Form\FormDataProvider;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseSystemLanguageRows;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Test case
 */
class DatabaseSystemLanguageRowsTest extends UnitTestCase {

	/**
	 * @var DatabaseSystemLanguageRows
	 */
	protected $subject;
	/**
	 * @var DatabaseConnection | ObjectProphecy
	 */
	protected $dbProphecy;

	public function setUp() {
		$this->dbProphecy = $this->prophesize(DatabaseConnection::class);
		$GLOBALS['TYPO3_DB'] = $this->dbProphecy->reveal();

		$this->subject = new DatabaseSystemLanguageRows();
	}

	/**
	 * @test
	 */
	public function addDataThrowsExceptionOnDatabaseError() {
		$this->dbProphecy->exec_SELECTgetRows(Argument::cetera())->willReturn(NULL);
		$this->dbProphecy->sql_error(Argument::cetera())->willReturn(NULL);
		$this->setExpectedException(\UnexpectedValueException::class,  $this->anything(), 1438170741);
		$this->subject->addData([]);
	}

	/**
	 * @test
	 */
	public function addDataSetsDefaultLanguageEntry() {
		$expected = [
			'systemLanguageRows' => [
				0 => [
					'uid' => 0,
					'title' => 'Default Language',
					'iso' => 'DEF',
				],
			],
		];
		$this->dbProphecy->exec_SELECTgetRows(Argument::cetera())->willReturn([]);
		$this->assertSame($expected, $this->subject->addData([]));
	}

	/**
	 * @test
	 */
	public function addDataResolvesLanguageIsocodeFromDatabaseField() {
		$dbRows = [
			[
				'uid' => 3,
				'title' => 'french',
				'language_isocode' => 'fr',
				'static_lang_isocode' => '',
			],
		];
		$this->dbProphecy->exec_SELECTgetRows('uid,title,language_isocode,static_lang_isocode', 'sys_language', 'pid=0 AND hidden=0')->willReturn($dbRows);
		$expected = [
			'systemLanguageRows' => [
				0 => [
					'uid' => 0,
					'title' => 'Default Language',
					'iso' => 'DEF',
				],
				3 => [
					'uid' => 3,
					'title' => 'french',
					'iso' => 'fr',
				],
			],
		];
		$this->assertSame($expected, $this->subject->addData([]));
	}

	/**
	 * @test
	 */
	public function addDataResolvesLanguageIsocodeFromStaticInfoTable() {
		if (ExtensionManagementUtility::isLoaded('static_info_tables') === FALSE) {
			$this->markTestSkipped('no ext:static_info_tables available');
		}
		$dbRows = [
			[
				'uid' => 3,
				'title' => 'french',
				'language_isocode' => '',
				'static_lang_isocode' => 42,
			],
		];
		$this->dbProphecy->exec_SELECTgetRows('uid,title,language_isocode,static_lang_isocode', 'sys_language', 'pid=0 AND hidden=0')->shouldBeCalled()->willReturn($dbRows);
		// Needed for backendUtility::getRecord()
		$GLOBALS['TCA']['static_languages'] = [ 'foo' ];
		$this->dbProphecy->exec_SELECTgetSingleRow('lg_iso_2', 'static_languages', 'uid=42')->shouldBeCalled()->willReturn( [ 'lg_iso_2' => 'FR' ] );
		$expected = [
			'systemLanguageRows' => [
				0 => [
					'uid' => 0,
					'title' => 'Default Language',
					'iso' => 'DEF',
				],
				3 => [
					'uid' => 3,
					'title' => 'french',
					'iso' => 'FR',
				],
			],
		];
		$this->assertSame($expected, $this->subject->addData([]));
	}

}