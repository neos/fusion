<?php
namespace Neos\Fusion\TypoScriptObjects;

/*
 * This file is part of the Neos.Fusion package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Exception\InvalidPositionException;
use Neos\Utility\PositionalArraySorter;
use Neos\Fusion;

/**
 * The old "COA" object
 */
class ArrayImplementation extends AbstractArrayTypoScriptObject
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function evaluate()
    {
        $sortedChildTypoScriptKeys = $this->sortNestedTypoScriptKeys();

        if (count($sortedChildTypoScriptKeys) === 0) {
            return null;
        }

        $output = '';
        foreach ($sortedChildTypoScriptKeys as $key) {
            try {
                $output .= $this->tsValue($key);
            } catch (\Exception $e) {
                $output .= $this->tsRuntime->handleRenderingException($this->path . '/' . $key, $e);
            }
        }

        return $output;
    }

    /**
     * Sort the TypoScript objects inside $this->properties depending on:
     * - numerical ordering
     * - position meta-property
     *
     * This will ignore all properties defined in "@ignoreProperties" in TypoScript
     *
     * @see PositionalArraySorter
     *
     * @return array an ordered list of keys
     * @throws Fusion\Exception if the positional string has an unsupported format
     */
    protected function sortNestedTypoScriptKeys()
    {
        $arraySorter = new PositionalArraySorter($this->properties, '__meta.position');
        try {
            $sortedTypoScriptKeys = $arraySorter->getSortedKeys();
        } catch (InvalidPositionException $exception) {
            throw new Fusion\Exception('Invalid position string', 1345126502, $exception);
        }

        foreach ($this->ignoreProperties as $ignoredPropertyName) {
            $key = array_search($ignoredPropertyName, $sortedTypoScriptKeys);
            if ($key !== false) {
                unset($sortedTypoScriptKeys[$key]);
            }
        }
        return $sortedTypoScriptKeys;
    }
}
