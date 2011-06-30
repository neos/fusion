<?php
namespace TYPO3\TypoScript;

/*                                                                        *
 * This script belongs to the FLOW3 package "TypoScript".                 *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License as published by the Free   *
 * Software Foundation, either version 3 of the License, or (at your      *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        *
 * You should have received a copy of the GNU General Public License      *
 * along with the script.                                                 *
 * If not, see http://www.gnu.org/licenses/gpl.html                       *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * A factory for TypoScript Objects
 *
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @scope singleton
 */
class ObjectFactory {

	/**
	 * @inject
	 * @var \TYPO3\FLOW3\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * Creates a new TypoScript object which is supposed to render the given node.
	 *
	 * @param \TYPO3\TYPO3CR\Domain\Model\NodeInterface $node The node
	 * @return mixed Either the TypoScript Object or FALSE if no object could be created for the given node
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function createByNode(\TYPO3\TYPO3CR\Domain\Model\NodeInterface $node) {
		$typoScriptObjectName = $this->getTypoScriptObjectNameByNode($node);

		$typoScriptObject = $this->objectManager->create($typoScriptObjectName);
		$typoScriptObject->setNode($node);
		return $typoScriptObject;
	}

	/**
	 * Figures out which TypoScript object to use for rendering a given node,
	 * and returns the class name as string.
	 *
	 * @param \TYPO3\TYPO3CR\Domain\Model\NodeInterface $node The node
	 * @return string The TypoScript object name with which the current node should be rendered with.
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 */
	public function getTypoScriptObjectNameByNode(\TYPO3\TYPO3CR\Domain\Model\NodeInterface $node) {
		$contentType = $node->getContentType();
		if (strpos($contentType, ':') !== FALSE) {
			list($packageKey, $typoScriptObjectName) = explode(':', $contentType);
			$possibleTypoScriptObjectName = str_replace('.', '\\', $packageKey) . '\\TypoScript\\' . $typoScriptObjectName;
			if ($this->objectManager->isRegistered($possibleTypoScriptObjectName) === TRUE) {
				return $possibleTypoScriptObjectName;
			}
		}

		return 'TYPO3\TYPO3\TypoScript\Node';
	}

	/**
	 * Creates a new TypoScript object by the specified name.
	 *
	 * @param string $typoScriptObjectName Short object name
	 * @return \TYPO3\TypoScript\ObjectInterface The TypoScript Object
	 * @author Robert Lemke <robert@typo3.org>
	 * @todo Needs some real implementation
	 */
	public function createByName($typoScriptObjectName) {
		return $this->objectManager->create('TYPO3\TYPO3\TypoScript\\' . $typoScriptObjectName);
	}
}
?>