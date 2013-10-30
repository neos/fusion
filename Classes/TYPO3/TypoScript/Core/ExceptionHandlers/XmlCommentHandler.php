<?php
namespace TYPO3\TypoScript\Core\ExceptionHandlers;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.TypoScript".      *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Creates xml comments from exceptions
 */
class XmlCommentHandler extends AbstractRenderingExceptionHandler {

	/**
	 * Provides an XML comment containing the exception
	 *
	 * @param array $typoScriptPath path causing the exception
	 * @param \Exception $exception exception to handle
	 * @param integer $referenceCode
	 * @return string
	 */
	protected function handle($typoScriptPath, \Exception $exception, $referenceCode) {
		if (isset($referenceCode)) {
			return sprintf(
				'<!-- Exception while rendering %s: %s (%s) -->',
				$this->formatScriptPath($typoScriptPath, ''),
				$exception->getMessage(),
				$referenceCode
			);
		} else {
			return sprintf(
				'<!-- Exception while rendering %s: %s -->',
				$this->formatScriptPath($typoScriptPath, ''),
				$exception->getMessage()
			);
		}
	}
}
