<?php
namespace TYPO3\TypoScript\View;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TypoScript".            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\View\AbstractView;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\Flow\Utility\Files;
use TYPO3\TypoScript\Core\Runtime;

/**
 * View for using TypoScript for standard MVC controllers.
 *
 * Recursively loads all TypoScript files from the configured path (By default that's Resources/Private/TypoScripts
 * of the current package) and then checks whether a TypoScript object for current controller and action can be found.
 *
 * If the controller class name is Foo\Bar\Baz\Controller\BlahController and the action is "index",
 * it checks for the TypoScript path Foo.Bar.Baz.BlahController.index.
 * If this path is found, then it is used for rendering. Otherwise, the $fallbackView is used.
 */
class TypoScriptView extends AbstractView {

	/**
	 * This contains the supported options, their default values, descriptions and types.
	 *
	 * @var array
	 */
	protected $supportedOptions = array(
		'typoScriptPathPatterns' => array(array('resource://@package/Private/TypoScripts'), 'TypoScript files will be recursively loaded from this paths.', 'array'),
		'typoScriptPath' => array(NULL, 'The TypoScript path which should be rendered; derived from the controller and action names or set by the user.', 'string'),
		'packageKey' => array(NULL, 'The package key where the TypoScript should be loaded from. If not given, is automatically derived from the current request.', 'string'),
		'debugMode' => array(FALSE, 'Flag to enable debug mode of the TypoScript runtime explicitly (overriding the global setting).', 'boolean')
	);

	/**
	 * @Flow\Inject
	 * @var \TYPO3\TypoScript\Core\Parser
	 */
	protected $typoScriptParser;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Mvc\View\ViewInterface
	 */
	protected $fallbackView;

	/**
	 * The parsed TypoScript array in its internal representation
	 *
	 * @var array
	 */
	protected $parsedTypoScript;

	/**
	 * Runtime cache of the TypoScript path which should be rendered; derived from the controller
	 * and action names or set by the user.
	 *
	 * @var string
	 */
	protected $typoScriptPath = NULL;

	/**
	 * if FALSE, the fallback view will never be used.
	 *
	 * @var boolean
	 */
	protected $fallbackViewEnabled = TRUE;

	/**
	 * The TypoScript Runtime
	 *
	 * @var Runtime
	 */
	protected $typoScriptRuntime = NULL;

	/**
	 * Reset runtime cache if an option is changed
	 *
	 * @param string $optionName
	 * @param mixed $value
	 * @return void
	 */
	public function setOption($optionName, $value) {
		$this->typoScriptPath = NULL;
		parent::setOption($optionName, $value);
	}

	/**
	 * Sets the TypoScript path to be rendered to an explicit value;
	 * to be used mostly inside tests.
	 *
	 * @param string $typoScriptPath
	 * @return void
	 */
	public function setTypoScriptPath($typoScriptPath) {
		$this->setOption('typoScriptPath', $typoScriptPath);
	}

	/**
	 * The package key where the TypoScript should be loaded from. If not given,
	 * is automatically derived from the current request.
	 *
	 * @param string $packageKey
	 * @return void
	 */
	public function setPackageKey($packageKey) {
		$this->setOption('packageKey', $packageKey);
	}

	/**
	 * @param string $pathPattern
	 * @return void
	 */
	public function setTypoScriptPathPattern($pathPattern) {
		$this->setOption('typoScriptPathPatterns', array($pathPattern));
	}

	/**
	 * @param array $pathPatterns
	 * @return void
	 */
	public function setTypoScriptPathPatterns(array $pathPatterns) {
		$this->setOption('typoScriptPathPatterns', $pathPatterns);
	}

	/**
	 * Disable the use of the Fallback View
	 *
	 * @return void
	 */
	public function disableFallbackView() {
		$this->fallbackViewEnabled = FALSE;
	}

	/**
	 * Re-Enable the use of the Fallback View. By default, it is enabled,
	 * so calling this method only makes sense if disableFallbackView() has
	 * been called before.
	 *
	 * @return void
	 */
	public function enableFallbackView() {
		$this->fallbackViewEnabled = TRUE;
	}

	/**
	 * Render the view
	 *
	 * @return string The rendered view
	 * @api
	 */
	public function render() {
		$this->initializeTypoScriptRuntime();
		if ($this->typoScriptRuntime->canRender($this->getTypoScriptPathForCurrentRequest()) || $this->fallbackViewEnabled === FALSE) {
			return $this->renderTypoScript();
		} else {
			return $this->renderFallbackView();
		}
	}

	/**
	 * Load the TypoScript Files form the defined
	 * paths and construct a Runtime from the
	 * parsed results
	 *
	 * @return void
	 */
	public function initializeTypoScriptRuntime() {
		if ($this->typoScriptRuntime === NULL) {
			$this->loadTypoScript();
			$this->typoScriptRuntime = new Runtime($this->parsedTypoScript, $this->controllerContext);
		}
		if (isset($this->options['debugMode'])) {
			$this->typoScriptRuntime->setDebugMode($this->options['debugMode']);
		}
	}

	/**
	 * Load TypoScript from the directories specified by $this->getOption('typoScriptPathPatterns')
	 *
	 * @return void
	 */
	protected function loadTypoScript() {
		$mergedTypoScriptCode = '';
		$typoScriptPathPatterns = $this->getOption('typoScriptPathPatterns');
		ksort($typoScriptPathPatterns);
		foreach ($typoScriptPathPatterns as $typoScriptPathPattern) {
			$typoScriptPathPattern = str_replace('@package', $this->getPackageKey(), $typoScriptPathPattern);
			$filePaths = Files::readDirectoryRecursively($typoScriptPathPattern, '.ts2');
			sort($filePaths);
			foreach ($filePaths as $filePath) {
				$mergedTypoScriptCode .= PHP_EOL . file_get_contents($filePath) . PHP_EOL;
			}
		}
		$this->parsedTypoScript = $this->typoScriptParser->parse($mergedTypoScriptCode);
	}

	/**
	 * Get the package key to load the TypoScript from. If set, $this->getOption('packageKey') is used.
	 * Otherwise, the current request is taken and the controller package key is extracted
	 * from there.
	 *
	 * @return string the package key to load TypoScript from
	 */
	protected function getPackageKey() {
		$packageKey = $this->getOption('packageKey');
		if ($packageKey !== NULL) {
			return $packageKey;
		} else {
			/** @var $request \TYPO3\Flow\Mvc\ActionRequest */
			$request = $this->controllerContext->getRequest();
			return $request->getControllerPackageKey();
		}
	}

	/**
	 * Determines the TypoScript path depending on the current controller and action
	 *
	 * @return string
	 */
	protected function getTypoScriptPathForCurrentRequest() {
		if ($this->typoScriptPath === NULL) {
			$typoScriptPath = $this->getOption('typoScriptPath');
			if ($typoScriptPath !== NULL) {
				$this->typoScriptPath = $typoScriptPath;
			} else {
				/** @var $request \TYPO3\Flow\Mvc\ActionRequest */
				$request = $this->controllerContext->getRequest();
				$typoScriptPathForCurrentRequest = $request->getControllerObjectName();
				$typoScriptPathForCurrentRequest = str_replace('\\Controller\\', '\\', $typoScriptPathForCurrentRequest);
				$typoScriptPathForCurrentRequest = str_replace('\\', '/', $typoScriptPathForCurrentRequest);
				$typoScriptPathForCurrentRequest = trim($typoScriptPathForCurrentRequest, '/');
				$typoScriptPathForCurrentRequest .= '/' . $request->getControllerActionName();

				$this->typoScriptPath = $typoScriptPathForCurrentRequest;
			}
		}
		return $this->typoScriptPath;
	}

	/**
	 * Determine whether we are able to find TypoScript at the requested position
	 *
	 * @return boolean TRUE if TypoScript exists at the current TypoScript path; FALSE otherwise
	 */
	protected function isTypoScriptFoundForCurrentRequest() {
		return (Arrays::getValueByPath($this->parsedTypoScript, str_replace('/', '.', $this->getTypoScriptPathForCurrentRequest())) !== NULL);
	}

	/**
	 * Render the given TypoScript and return the rendered page
	 *
	 * @return string
	 */
	protected function renderTypoScript() {
		$this->typoScriptRuntime->pushContextArray($this->variables);
		$output = $this->typoScriptRuntime->render($this->getTypoScriptPathForCurrentRequest());
		$this->typoScriptRuntime->popContext();
		return $output;
	}

	/**
	 * Initialize and render the fallback view
	 *
	 * @return string
	 */
	public function renderFallbackView() {
		$this->fallbackView->setControllerContext($this->controllerContext);
		$this->fallbackView->assignMultiple($this->variables);
		return $this->fallbackView->render();
	}
}
