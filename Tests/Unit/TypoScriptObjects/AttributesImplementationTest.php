<?php
namespace TYPO3\TypoScript\Tests\Unit\TypoScriptObjects;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.TypoScript".      *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Reflection\ObjectAccess;
use TYPO3\Flow\Tests\UnitTestCase;
use TYPO3\TypoScript\Core\Runtime;
use TYPO3\TypoScript\TypoScriptObjects\AttributesImplementation;

/**
 * Testcase for the TypoScript Attributes object
 */
class AttributesImplementationTest extends UnitTestCase {

	/**
	 * @var Runtime
	 */
	protected $mockTsRuntime;

	public function setUp() {
		parent::setUp();
		$this->mockTsRuntime = $this->getMockBuilder('TYPO3\TypoScript\Core\Runtime')->disableOriginalConstructor()->getMock();
	}

	public function attributeExamples() {
		return array(
			'null' => array(NULL, ''),
			'empty array' => array(array(), ''),
			'simple array' => array(array('attributeName1' => 'attributeValue1'), ' attributeName1="attributeValue1"'),
			'encoding' => array(array('spec<ial' => 'chara>cters'), ' spec&lt;ial="chara&gt;cters"'),
			'array attributes' => array(array('class' => array('icon', 'icon-neos')), ' class="icon icon-neos"'),
		);
	}

	/**
	 * @test
	 * @dataProvider attributeExamples
	 */
	public function evaluateTests($properties, $expectedOutput) {
		$path = 'attributes/test';
		$this->mockTsRuntime->expects($this->any())->method('evaluate')->will($this->returnCallback(function($evaluatePath, $that) use ($path, $properties) {
			$relativePath = str_replace($path . '/', '', $evaluatePath);
			return ObjectAccess::getProperty($properties, $relativePath);
		}));

		$typoScriptObjectName = 'TYPO3.TypoScript:Attributes';
		$renderer = new AttributesImplementation($this->mockTsRuntime, $path, $typoScriptObjectName);
		if ($properties !== NULL) {
			foreach ($properties as $name => $value) {
				ObjectAccess::setProperty($renderer, $name, $value);
			}
		}

		$result = $renderer->evaluate();
		$this->assertEquals($expectedOutput, $result);
	}

}