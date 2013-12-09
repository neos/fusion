<?php
namespace TYPO3\TypoScript\TypoScriptObjects;

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

/**
 * A TypoScript UriBuilder object
 *
 * The following TS properties are evaluated:
 *  * package
 *  * subpackage
 *  * controller
 *  * action
 *  * arguments
 *  * format
 *  * section
 *  * additionalParams
 *  * addQueryString
 *  * argumentsToBeExcludedFromQueryString
 *  * absolute
 *
 * See respective getters for descriptions
 */
class UriBuilderImplementation extends TemplateImplementation {

	/**
	 * Key of the target package
	 *
	 * @return string
	 */
	public function getPackage() {
		return $this->tsValue('package');
	}

	/**
	 * Key of the target sub package
	 *
	 * @return string
	 */
	public function getSubpackage() {
		return $this->tsValue('subpackage');
	}

	/**
	 * Target controller name
	 *
	 * @return string
	 */
	public function getController() {
		return $this->tsValue('controller');
	}

	/**
	 * Target controller action name
	 *
	 * @return string
	 */
	public function getAction() {
		return $this->tsValue('action');
	}

	/**
	 * Controller arguments
	 *
	 * @return array
	 */
	public function getArguments() {
		return $this->tsValue('arguments');
	}

	/**
	 * The requested format, for example "html"
	 *
	 * @return string
	 */
	public function getFormat() {
		return $this->tsValue('format');
	}

	/**
	 * The anchor to be appended to the URL
	 *
	 * @return string
	 */
	public function getSection() {
		return $this->tsValue('section');
	}

	/**
	 * Additional query parameters that won't be prefixed like $arguments (overrule $arguments)
	 *
	 * @return array
	 */
	public function getAdditionalParams() {
		return $this->tsValue('additionalParams');
	}

	/**
	 * Arguments to be removed from the URI. Only active if addQueryString = TRUE
	 *
	 * @return array
	 */
	public function getArgumentsToBeExcludedFromQueryString() {
		return $this->tsValue('argumentsToBeExcludedFromQueryString');
	}

	/**
	 * If TRUE, the current query parameters will be kept in the URI
	 *
	 * @return boolean
	 */
	public function isAddQueryString() {
		return (boolean)$this->tsValue('addQueryString');
	}

	/**
	 * If TRUE, an absolute URI is rendered
	 *
	 * @return boolean
	 */
	public function isAbsolute() {
		return (boolean)$this->tsValue('absolute');
	}

	/**
	 * @return string
	 */
	public function evaluate() {
		$controllerContext = $this->getTsRuntime()->getControllerContext();
		$uriBuilder = $controllerContext->getUriBuilder()->reset();

		$format = $this->getFormat();
		if ($format !== NULL) {
			$uriBuilder->setFormat($format);
		}

		$additionalParams = $this->getAdditionalParams();
		if ($additionalParams !== NULL) {
			$uriBuilder->setArguments($additionalParams);
		}

		$argumentsToBeExcludedFromQueryString = $this->getArgumentsToBeExcludedFromQueryString();
		if ($argumentsToBeExcludedFromQueryString !== NULL) {
			$uriBuilder->setArgumentsToBeExcludedFromQueryString($argumentsToBeExcludedFromQueryString);
		}

		$absolute = $this->isAbsolute();
		if ($absolute === TRUE) {
			$uriBuilder->setCreateAbsoluteUri(TRUE);
		}

		$section = $this->getSection();
		if ($section !== NULL) {
			$uriBuilder->setSection($section);
		}

		$addQueryString = $this->isAddQueryString();
		if ($addQueryString === TRUE) {
			$uriBuilder->setAddQueryString(TRUE);
		}

		try {
			return $uriBuilder->uriFor(
				$this->getAction(),
				$this->getArguments(),
				$this->getController(),
				$this->getPackage(),
				$this->getSubpackage()
			);
		} catch(\Exception $exception) {
			return $this->tsRuntime->handleRenderingException($this->path, $exception);
		}
	}
}
