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
use TYPO3\Flow\Utility\Exception\InvalidPositionException;
use TYPO3\Flow\Utility\PositionalArraySorter;
use TYPO3\TypoScript;

/**
 * The old "COA" object
 */
class ArrayImplementation extends AbstractTypoScriptObject {

	/**
	 * {@inheritdoc}
	 *
	 * @return string
	 */
	public function evaluate() {
		$sortedChildTypoScriptKeys = $this->sortNestedTypoScriptKeys();

		if (count($sortedChildTypoScriptKeys) === 0) {
			return NULL;
		}

		$output = '';
		foreach ($sortedChildTypoScriptKeys as $key) {
			$output .= $this->tsValue($key);
		}

		return $output;
	}

	/**
	 * Sort the TypoScript objects inside $this->subElements depending on:
	 * - numerical ordering
	 * - position meta-property
	 *
	 * @see \TYPO3\Flow\Utility\PositionalArraySorter
	 * TODO Detect circles in after / before dependencies
	 *
	 * @return array an ordered list of keys
	 * @throws TypoScript\Exception if the positional string has an unsupported format
	 */
	protected function sortNestedTypoScriptKeys() {
		$arraySorter = new PositionalArraySorter($this->properties, '__meta.position');
		try {
			$sortedTypoScriptKeys = $arraySorter->getSortedKeys();
		} catch (InvalidPositionException $exception) {
			throw new TypoScript\Exception('Invalid position string', 1345126502, $exception);
		}
		return $sortedTypoScriptKeys;
	}
}
