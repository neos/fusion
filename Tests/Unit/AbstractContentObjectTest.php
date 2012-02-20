<?php
namespace TYPO3\TypoScript\Tests\Unit;

/*                                                                        *
 * This script belongs to the FLOW3 package "TypoScript".                 *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Testcase for the AbstractContentObject
 *
 */
class AbstractContentObjectTest extends \TYPO3\FLOW3\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\TypoScript\AbstractContentObject
	 */
	protected $abstractContentObject;

	/**
	 * @var \TYPO3\TYPO3\TypoScript\Template
	 */
	protected $mockTemplate;

	/**
	 * @var \TYPO3\TypoScript\RenderingContext
	 */
	protected $mockRenderingContext;

	/**
	 */
	protected function setUp() {
		$this->abstractContentObject = $this->getMock('TYPO3\TypoScript\AbstractContentObject', array('count'));
		$this->mockTemplate = $this->getMock('TYPO3\TYPO3\TypoScript\Template');
		$this->abstractContentObject->setTemplate($this->mockTemplate);
		$this->mockRenderingContext = $this->getMock('TYPO3\TypoScript\RenderingContext');
		$this->abstractContentObject->setRenderingContext($this->mockRenderingContext);
	}

	/**
	 * @test
	 */
	public function renderSetsTemplateRenderingContext() {
		$this->mockTemplate->expects($this->once())->method('setRenderingContext')->with($this->mockRenderingContext);
		$this->abstractContentObject->render();
	}

	/**
	 * @test
	 */
	public function renderAssignsNodeToTemplate() {
		$mockNode = $this->getMock('TYPO3\TYPO3CR\Domain\Model\NodeInterface');
		$mockNode->expects($this->any())->method('isAccessible')->will($this->returnValue(TRUE));
		$this->abstractContentObject->setNode($mockNode);

		$this->mockTemplate->expects($this->once())->method('assign')->with('node', $mockNode);
		$this->abstractContentObject->render();
	}

	/**
	 * @test
	 */
	public function renderReturnsRenderedTemplateIfNodeIsAccessible() {
		$mockNode = $this->getMock('TYPO3\TYPO3CR\Domain\Model\NodeInterface');
		$mockNode->expects($this->any())->method('isAccessible')->will($this->returnValue(TRUE));
		$this->abstractContentObject->setNode($mockNode);

		$expectedResult = 'rendered Template';
		$this->mockTemplate->expects($this->once())->method('render')->will($this->returnValue($expectedResult));

		$actualResult = $this->abstractContentObject->render();
		$this->assertSame($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function renderReturnsEmptyStringIfNodeIsNotAccessible() {
		$mockNode = $this->getMock('TYPO3\TYPO3CR\Domain\Model\NodeInterface');
		$mockNode->expects($this->any())->method('isAccessible')->will($this->returnValue(FALSE));
		$this->abstractContentObject->setNode($mockNode);

		$this->mockTemplate->expects($this->never())->method('render');

		$this->assertEmpty($this->abstractContentObject->render());
	}

}

?>