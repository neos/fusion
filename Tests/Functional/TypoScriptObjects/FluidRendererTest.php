<?php
namespace TYPO3\TypoScript\Tests\Functional\TypoScriptObjects;

/*                                                                        *
 * This script belongs to the FLOW3 package "TYPO3.TypoScript".           *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Testcase for the TypoScript Fluid Renderer
 *
 */
class FluidRendererTest extends AbstractTypoScriptObjectTest {

	/**
	 * @test
	 */
	public function basicFluidTemplateCanBeUsedForRendering() {
		$view = $this->buildView();

		$view->setTypoScriptPath('fluidRenderer/basicTemplate');
		$this->assertEquals('Test Templatefoo', $view->render());
	}

	/**
	 * @test
	 */
	public function customPartialPathCanBeSetOnRendering() {
		$view = $this->buildView();

		$view->setTypoScriptPath('fluidRenderer/partial');
		$this->assertEquals('Test Template--partial contents', $view->render());
	}

	/**
	 * @test
	 */
	public function customLayoutPathCanBeSetOnRendering() {
		$view = $this->buildView();

		$view->setTypoScriptPath('fluidRenderer/layout');
		$this->assertEquals('layout start -- Test Template -- layout end', $view->render());
	}

}
?>