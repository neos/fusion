<?php
namespace TYPO3\TypoScript\Core;

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
use TYPO3\Flow\Utility\Arrays;
use TYPO3\TypoScript\Exception;

/**
 * The TypoScript Parser
 *
 * @api
 */
class Parser implements ParserInterface {

	const SCAN_PATTERN_COMMENT = '/
		^\s*                      # beginning of line; with numerous whitespace
		(
			\#                     # this can be a comment char
			|\/\/                  # or two slashes
			|\/\*                  # or slash followed by star
		)
	/x';
	const SCAN_PATTERN_OPENINGCONFINEMENT = '/
		^\s*                      # beginning of line; with numerous whitespace
		[a-zA-Z0-9():@\-]*          # first part of a TS path
		(?:                       # followed by multiple .<tsPathPart> sections:
			\.
			[a-zA-Z0-9():@\-]*
		)*
		\s*                       # followed by multiple whitespace
		\{                        # followed by opening {
		\s*$                      # followed by multiple whitespace (possibly) and nothing else.
	/x';

	const SCAN_PATTERN_CLOSINGCONFINEMENT = '/
		^\s*                      # beginning of line; with numerous whitespace
		\}                        # closing confinement
		\s*$                      # followed by multiple whitespace (possibly) and nothing else.
	/x';
	const SCAN_PATTERN_DECLARATION = '/
		^\s*                      # beginning of line; with numerous whitespace
		(include|namespace)       # followed by namespace or include
		\s*:                      # followed by numerous whitespace and a colon
	/x';
	const SCAN_PATTERN_OBJECTDEFINITION = '/
		^\s*                      # beginning of line; with numerous whitespace
		[a-zA-Z0-9.\\\\$():@\-]+
		\s*
		(=|<|>)
	/x';
	const SCAN_PATTERN_OBJECTPATH = '/
		^
			\.?
			(?:
				@?[a-zA-Z0-9:\-]*
				| prototype\([a-zA-Z0-9.:]+\)
			)
			(?:
				\.
				(?:
					@?[a-zA-Z0-9:\-]*
					| prototype\([a-zA-Z0-9.:]+\)
				)
			)*
		$
	/x';

	/**
	 * Split an object path like "foo.bar.baz.quux" or "foo.prototype(TYPO3.TypoScript:Something).bar.baz"
	 * at the dots (but not the dots inside the prototype definition prototype(...))
	 */
	const SPLIT_PATTERN_OBJECTPATH = '/
		\.                        # we split at dot characters...
		(?!                       # which are not inside prototype(...). Thus, the dot does NOT match IF it is followed by:
			[^(]*                  # - any character except (
			\)                     # - the character )
		)
	/x';

	/**
	 * Analyze an object path segment like "foo" or "prototype(TYPO3.TypoScript:Something)"
	 * and detect the latter
	 */
	const SCAN_PATTERN_OBJECTPATHSEGMENT_IS_PROTOTYPE = '/
		^
			prototype\([a-zA-Z0-9:.]+\)
		$
	/x';

	const SPLIT_PATTERN_COMMENTTYPE = '/.*(#|\/\/|\/\*|\*\/).*/';
	const SPLIT_PATTERN_DECLARATION = '/([a-zA-Z]+[a-zA-Z0-9]*)\s*:(.*)/';
	const SPLIT_PATTERN_NAMESPACEDECLARATION = '/\s*([a-zA-Z]+[a-zA-Z0-9]*)\s*=\s*([a-zA-Z0-9\.]+)\s*$/';
	const SPLIT_PATTERN_OBJECTDEFINITION = '/
		^\s*                      # beginning of line; with numerous whitespace
		(?P<ObjectPath>           # begin ObjectPath

			\.?
			(?:
				@?[a-zA-Z0-9:\-]*
				|prototype\([a-zA-Z0-9.:]+\)
			)
			(?:
				\.
				(?:
					@?[a-zA-Z0-9:\-]*
					|prototype\([a-zA-Z0-9.:]+\)
				)
			)*
		)
		\s*
		(?P<Operator>             # the operators which are supported
			=|<|>
		)
		\s*
		(?P<Value>                # the remaining line inside the value
			.*?
		)
		\s*
		(?P<OpeningConfinement>
			\{                     # optionally followed by an opening confinement
		)?
		\s*$
	/x';
	const SPLIT_PATTERN_VALUENUMBER = '/^\s*-?\d+\s*$/';
	const SPLIT_PATTERN_VALUEFLOATNUMBER = '/^\s*-?\d+(\.\d+)?\s*$/';
	const SPLIT_PATTERN_VALUELITERAL = '/^"((?:\\\\.|[^\\\\"])*)"|\'((?:\\\\.|[^\\\\\'])*)\'$/';
	const SPLIT_PATTERN_VALUEMULTILINELITERAL = '/^(?P<DoubleQuoteChar>")(?P<DoubleQuoteValue>(?:\\\\.|[^\\\\"])*)$|(?P<SingleQuoteChar>\')(?P<SingleQuoteValue>(?:\\\\.|[^\\\\\'])*)$/';
	const SPLIT_PATTERN_VALUEBOOLEAN = '/^\s*(TRUE|FALSE|true|false)\s*$/';

	const SCAN_PATTERN_VALUEOBJECTTYPE = '/
		^\s*                      # beginning of line; with numerous whitespace
		(?:                       # non-capturing submatch containing the namespace followed by ":" (optional)
			(?P<namespace>
				[a-zA-Z0-9.]+       # namespace alias (cms, …) or fully qualified namespace (TYPO3.Neos, …)
			)
			:                      # : as delimiter
		)?
		(?P<unqualifiedType>
			[a-zA-Z0-9.]+          # the unqualified type
		)
		\s*$
	/x';

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * The TypoScript object tree, created by this parser.
	 * @var array
	 */
	protected $objectTree = array();

	/**
	 * The line number which is currently processed
	 * @var integer
	 */
	protected $currentLineNumber = 1;

	/**
	 * An array of strings of the source code which has
	 * @var array
	 */
	protected $currentSourceCodeLines = array();

	/**
	 * The current object path context as defined by confinements.
	 * @var array
	 */
	protected $currentObjectPathStack = array();

	/**
	 * Determines if a block comment is currently active or not.
	 * @var boolean
	 */
	protected $currentBlockCommentState = FALSE;

	/**
	 * An optional context path which is used as a prefix for inclusion of further
	 * TypoScript files
	 * @var string
	 */
	protected $contextPathAndFilename = NULL;

	/**
	 * Namespaces used for resolution of TypoScript object names. These namespaces
	 * are a mapping from a user defined key (alias) to a package key (the namespace).
	 * By convention, the namespace should be a package key, but other strings would
	 * be possible, too. Note that, in order to resolve an object type, a prototype
	 * with that namespace and name must be defined elsewhere.
	 *
	 * These namespaces are _not_ used for resolution of processor class names.
	 * @var array
	 */
	protected $objectTypeNamespaces = array(
		'default' => 'TYPO3.TypoScript'
	);

	/**
	 * Parses the given TypoScript source code and returns an object tree
	 * as the result.
	 *
	 * @param string $sourceCode The TypoScript source code to parse
	 * @param string $contextPathAndFilename An optional path and filename to use as a prefix for inclusion of further TypoScript files
	 * @param array $objectTreeUntilNow Used internally for keeping track of the built object tree
	 * @param boolean $buildPrototypeHierarchy Merge prototype configurations or not. Will be FALSE for includes to only do that once at the end.
	 * @return array A TypoScript object tree, generated from the source code
	 * @throws \TYPO3\TypoScript\Exception
	 * @api
	 */
	public function parse($sourceCode, $contextPathAndFilename = NULL, array $objectTreeUntilNow = array(), $buildPrototypeHierarchy = TRUE) {
		if (!is_string($sourceCode)) {
			throw new Exception('Cannot parse TypoScript - $sourceCode must be of type string!', 1180203775);
		}
		$this->initialize();
		$this->objectTree = $objectTreeUntilNow;
		$this->contextPathAndFilename = $contextPathAndFilename;
		$this->currentSourceCodeLines = explode(chr(10), $sourceCode);
		while(($typoScriptLine = $this->getNextTypoScriptLine()) !== FALSE) {
			$this->parseTypoScriptLine($typoScriptLine);
		}

		if ($buildPrototypeHierarchy) {
			$this->buildPrototypeHierarchy();
		}
		return $this->objectTree;
	}

	/**
	 * Sets the given alias to the specified namespace.
	 *
	 * The namespaces defined through this setter or through a "namespace" declaration
	 * in one of the TypoScripts are used to resolve a fully qualified TypoScript
	 * object name while parsing TypoScript code.
	 *
	 * The alias is the handle by wich the namespace can be referred to.
	 * The namespace is, by convention, a package key which must correspond to a
	 * namespace used in the prototype definitions for TypoScript object types.
	 *
	 * The special alias "default" is used as a fallback for resolution of unqualified
	 * TypoScript object types.
	 *
	 * @param string $alias An alias for the given namespace, for example "neos"
	 * @param string $namespace The namespace, for example "TYPO3.Neos"
	 * @return void
	 * @throws \TYPO3\TypoScript\Exception
	 * @api
	 */
	public function setObjectTypeNamespace($alias, $namespace) {
		if (!is_string($alias)) {
			throw new Exception('The alias of a namespace must be valid string!', 1180600696);
		}
		if (!is_string($namespace)) {
			throw new Exception('The namespace must be of type string!', 1180600697);
		}
		$this->objectTypeNamespaces[$alias] = $namespace;
	}

	/**
	 * Initializes the TypoScript parser
	 *
	 * @return void
	 */
	protected function initialize() {
		$this->currentLineNumber = 1;
		$this->currentObjectPathStack = array();
		$this->currentSourceCodeLines = array();
		$this->currentBlockCommentState = FALSE;
		$this->objectTree = array();
	}

	/**
	 * Get the next, unparsed line of TypoScript from this->currentSourceCodeLines and increase the pointer
	 *
	 * @return string next line of typoscript to parse
	 */
	protected function getNextTypoScriptLine() {
		$typoScriptLine = current($this->currentSourceCodeLines);
		next($this->currentSourceCodeLines);
		$this->currentLineNumber ++;
		return $typoScriptLine;
	}

	/**
	 * Parses one line of TypoScript
	 *
	 * @param string $typoScriptLine One line of TypoScript code
	 * @return void
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function parseTypoScriptLine($typoScriptLine) {
		$typoScriptLine = trim($typoScriptLine);

		if ($this->currentBlockCommentState === TRUE) {
			$this->parseComment($typoScriptLine);
		} else {
			if ($typoScriptLine === '') {
				return;
			} elseif (preg_match(self::SCAN_PATTERN_COMMENT, $typoScriptLine)) {
				$this->parseComment($typoScriptLine);
			} elseif (preg_match(self::SCAN_PATTERN_OPENINGCONFINEMENT, $typoScriptLine)) {
				$this->parseConfinementBlock($typoScriptLine, TRUE);
			} elseif (preg_match(self::SCAN_PATTERN_CLOSINGCONFINEMENT, $typoScriptLine)) {
				$this->parseConfinementBlock($typoScriptLine, FALSE);
			} elseif (preg_match(self::SCAN_PATTERN_DECLARATION, $typoScriptLine)) {
				$this->parseDeclaration($typoScriptLine);
			} elseif (preg_match(self::SCAN_PATTERN_OBJECTDEFINITION, $typoScriptLine)) {
				$this->parseObjectDefinition($typoScriptLine);
			} else {
				throw new Exception('Syntax error in line ' . $this->currentLineNumber . '. (' . $typoScriptLine . ')', 1180547966);
			}
		}
	}

	/**
	 * Parses a line with comments or a line while parsing is in block comment mode.
	 *
	 * @param string $typoScriptLine One line of TypoScript code
	 * @return void
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function parseComment($typoScriptLine) {
		if (preg_match(self::SPLIT_PATTERN_COMMENTTYPE, $typoScriptLine, $matches, PREG_OFFSET_CAPTURE) === 1) {
			switch ($matches[1][0]) {
				case '/*' :
					$this->currentBlockCommentState = TRUE;
					break;
				case '*/' :
					if ($this->currentBlockCommentState !== TRUE) {
						throw new Exception('Unexpected closing block comment without matching opening block comment.', 1180615119);
					}
					$this->currentBlockCommentState = FALSE;
					$this->parseTypoScriptLine(substr($typoScriptLine, ($matches[1][1] + 2)));
					break;
				case '#' :
				case '//' :
				default :
					break;
			}
		} elseif ($this->currentBlockCommentState === FALSE) {
			throw new Exception('No comment type matched although the comment scan regex matched the TypoScript line (' . $typoScriptLine . ').', 1180614895);
		}
	}

	/**
	 * Parses a line which opens or closes a confinement
	 *
	 * @param string $typoScriptLine One line of TypoScript code
	 * @param boolean $isOpeningConfinement Set to TRUE, if an opening confinement is to be parsed and FALSE if it's a closing confinement.
	 * @return void
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function parseConfinementBlock($typoScriptLine, $isOpeningConfinement) {
		if ($isOpeningConfinement) {
			$result = trim(trim(trim($typoScriptLine), '{'));
			array_push($this->currentObjectPathStack, $this->getCurrentObjectPathPrefix() . $result);
		} else {
			if (count($this->currentObjectPathStack) < 1) {
				throw new Exception('Unexpected closing confinement without matching opening confinement. Check the number of your curly braces.', 1181575973);
			}
			array_pop($this->currentObjectPathStack);
		}
	}

	/**
	 * Parses a parser declaration of the form "declarationtype: declaration".
	 *
	 * @param string $typoScriptLine One line of TypoScript code
	 * @return void
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function parseDeclaration($typoScriptLine) {
		$result = preg_match(self::SPLIT_PATTERN_DECLARATION, $typoScriptLine, $matches);
		if ($result !== 1 || count($matches) != 3) {
			throw new Exception('Invalid declaration "' . $typoScriptLine . '"', 1180544656);
		}

		switch ($matches[1]) {
			case 'namespace' :
				$this->parseNamespaceDeclaration($matches[2]);
				break;
			case 'include' :
				$this->parseInclude($matches[2]);
				break;
		}
	}

	/**
	 * Parses an object definition.
	 *
	 * @param string $typoScriptLine One line of TypoScript code
	 * @return void
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function parseObjectDefinition($typoScriptLine) {
		$result = preg_match(self::SPLIT_PATTERN_OBJECTDEFINITION, $typoScriptLine, $matches);
		if ($result !== 1) {
			throw new Exception('Invalid object definition "' . $typoScriptLine . '"', 1180548488);
		}

		$objectPath = $this->getCurrentObjectPathPrefix() . $matches['ObjectPath'];
		switch ($matches['Operator']) {
			case '=' :
				$this->parseValueAssignment($objectPath, $matches['Value']);
				break;
			case '>' :
				$this->parseValueUnAssignment($objectPath);
				break;
			case '<' :
				$this->parseValueCopy($matches['Value'], $objectPath);
				break;
		}

		if (isset($matches['OpeningConfinement'])) {
			$this->parseConfinementBlock($matches['ObjectPath'], TRUE);
		}
	}

	/**
	 * Parses a value operation of the type "assignment".
	 *
	 * @param string $objectPath The object path as a string
	 * @param string $value The unparsed value as a string
	 * @return void
	 */
	protected function parseValueAssignment($objectPath, $value) {
		$processedValue = $this->getProcessedValue($value);
		$this->setValueInObjectTree($this->getParsedObjectPath($objectPath), $processedValue);
	}

	/**
	 * Unsets the object, property or variable specified by the object path.
	 *
	 * @param string $objectPath The object path as a string
	 * @return void
	 */
	protected function parseValueUnAssignment($objectPath) {
		$objectPathArray = $this->getParsedObjectPath($objectPath);
		$this->setValueInObjectTree($objectPathArray, NULL);
	}

	/**
	 * Copies the object or value specified by sourcObjectPath and assigns
	 * it to targetObjectPath.
	 *
	 * @param string $sourceObjectPath Specifies the location in the object tree from where the object or value will be taken
	 * @param string $targetObjectPath Specifies the location in the object tree where the copy will be stored
	 * @return void
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function parseValueCopy($sourceObjectPath, $targetObjectPath) {
		$sourceObjectPathArray = $this->getParsedObjectPath($sourceObjectPath);
		$targetObjectPathArray = $this->getParsedObjectPath($targetObjectPath);

		$sourceIsPrototypeDefinition = (count($sourceObjectPathArray) >= 2 && $sourceObjectPathArray[count($sourceObjectPathArray) - 2] === '__prototypes');
		$targetIsPrototypeDefinition = (count($targetObjectPathArray) >= 2 && $targetObjectPathArray[count($targetObjectPathArray) - 2] === '__prototypes');

		if ($sourceIsPrototypeDefinition || $targetIsPrototypeDefinition) {
				// either source or target are a prototype definition
			if ($sourceIsPrototypeDefinition && $targetIsPrototypeDefinition && count($sourceObjectPathArray) === 2 && count($targetObjectPathArray) === 2) {
					// both are a prototype definition and the path has length 2: this means
					// it must be of the form "prototype(Foo) < prototype(Bar)"
				$targetObjectPathArray[] = '__prototypeObjectName';
				$this->setValueInObjectTree($targetObjectPathArray, end($sourceObjectPathArray));
			} elseif ($sourceIsPrototypeDefinition && $targetIsPrototypeDefinition) {
					// Both are prototype definitions, but at least one is nested (f.e. foo.prototype(Bar))
					// Currently, it is not supported to override the prototypical inheritance in
					// parts of the TS rendering tree.
					// Although this might work conceptually, it makes reasoning about the prototypical
					// inheritance tree a lot more complex; that's why we forbid it right away.
				throw new Exception('Tried to parse "' . $targetObjectPath . '" < "' . $sourceObjectPath . '", however one of the sides is nested (e.g. foo.prototype(Bar)). Setting up prototype inheritance is only supported at the top level: prototype(Foo) < prototype(Bar)', 1358418019);
			} else {
					// Either "source" or "target" are no prototypes. We do not support copying a
					// non-prototype value to a prototype value or vice-versa.
				throw new Exception('Tried to parse "' . $targetObjectPath . '" < "' . $sourceObjectPath . '", however one of the sides is no prototype definition of the form prototype(Foo). It is only allowed to build inheritance chains with prototype objects.', 1358418015);
			}
		} else {
			$originalValue = $this->getValueFromObjectTree($sourceObjectPathArray);
			$value = is_object($originalValue) ? clone $originalValue : $originalValue;

			$this->setValueInObjectTree($targetObjectPathArray, $value);
		}
	}

	/**
	 * Parses a namespace declaration and stores the result in the namespace registry.
	 *
	 * @param string $namespaceDeclaration The namespace declaration, for example "neos = TYPO3.Neos"
	 * @return void
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function parseNamespaceDeclaration($namespaceDeclaration) {
		$result = preg_match(self::SPLIT_PATTERN_NAMESPACEDECLARATION, $namespaceDeclaration, $matches);
		if ($result !== 1 || count($matches) !== 3) {
			throw new Exception('Invalid namespace declaration "' . $namespaceDeclaration . '"', 1180547190);
		}

		$namespaceAlias = $matches[1];
		$namespacePackageKey = $matches[2];
		$this->objectTypeNamespaces[$namespaceAlias] = $namespacePackageKey;
	}

	/**
	 * Parse an include file. Currently, we start a new parser object; but we could as well re-use
	 * the given one.
	 *
	 * @param string $include The include value, for example " FooBar" or " resource://...."
	 * @return void
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function parseInclude($include) {
		$include = trim($include);
		$parser = clone $this;
		if (strpos($include, 'resource://') === 0) {
			if (!file_exists($include)) {
				throw new Exception(sprintf('Could not include TypoScript file "%s"', $include), 1347977017);
			}
			$this->objectTree = $parser->parse(file_get_contents($include), $include, $this->objectTree, FALSE);
		} elseif ($this->contextPathAndFilename !== NULL) {
			$include = dirname($this->contextPathAndFilename) . '/' . $include;
			if (!file_exists($include)) {
				throw new Exception(sprintf('Could not include TypoScript file "%s"', $include), 1347977016);
			}
			$this->objectTree = $parser->parse(file_get_contents($include), $include, $this->objectTree, FALSE);
		} else {
			throw new Exception('Relative file inclusions are only possible if a context path and filename has been passed as second argument to parse()', 1329806940);
		}
	}

	/**
	 * Parse an object path specified as a string and returns an array.
	 *
	 * @param string $objectPath The object path to parse
	 * @return array An object path array
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function getParsedObjectPath($objectPath) {
		if (preg_match(self::SCAN_PATTERN_OBJECTPATH, $objectPath) === 1) {
			if ($objectPath[0] == '.') {
				$objectPath = $this->getCurrentObjectPathPrefix() . substr($objectPath, 1);
			}

			$objectPathArray = array();
			foreach (preg_split(self::SPLIT_PATTERN_OBJECTPATH, $objectPath) as $objectPathSegment) {
				if ($objectPathSegment[0] === '@') {
					$objectPathArray[] = '__meta';
					$objectPathArray[] = substr($objectPathSegment, 1);
				} elseif (preg_match(self::SCAN_PATTERN_OBJECTPATHSEGMENT_IS_PROTOTYPE, $objectPathSegment)) {
					$objectPathArray[] = '__prototypes';

					$unexpandedObjectType = substr($objectPathSegment, 10, -1);
					$objectTypeParts = explode(':', $unexpandedObjectType);
					if (!isset($objectTypeParts[1])) {
						$fullyQualifiedObjectType = $this->objectTypeNamespaces['default'] . ':' . $objectTypeParts[0];
					} elseif (isset($this->objectTypeNamespaces[$objectTypeParts[0]])) {
						$fullyQualifiedObjectType = $this->objectTypeNamespaces[$objectTypeParts[0]] . ':' . $objectTypeParts[1];
					} else {
						$fullyQualifiedObjectType = $unexpandedObjectType;
					}
					$objectPathArray[] = $fullyQualifiedObjectType;
				} else {
					$objectPathArray[] = $objectPathSegment;
				}
			}
		} else {
			throw new Exception('Syntax error: Invalid object path "' . $objectPath . '".', 1180603499);
		}

		return $objectPathArray;
	}

	/**
	 * Parses the given value (which may be a literal, variable or object type) and
	 * returns the evaluated result, including variables replaced by their actual value.
	 *
	 * @param string $unparsedValue The unparsed value
	 * @return mixed The processed value
	 * @throws \TYPO3\TypoScript\Exception
	 */
	protected function getProcessedValue($unparsedValue) {
		if (preg_match(self::SPLIT_PATTERN_VALUENUMBER, $unparsedValue, $matches) === 1) {
			$processedValue = intval($unparsedValue);
		} elseif (preg_match(self::SPLIT_PATTERN_VALUEFLOATNUMBER, $unparsedValue, $matches) === 1) {
			$processedValue = floatval($unparsedValue);
		} elseif (preg_match(\TYPO3\Eel\Package::EelExpressionRecognizer, $unparsedValue, $matches) === 1) {
			$processedValue = array(
				'__eelExpression' => $matches[1],
				'__value' => NULL,
				'__objectType' => NULL
			);
		} elseif (preg_match(self::SPLIT_PATTERN_VALUELITERAL, $unparsedValue, $matches) === 1) {
			$processedValue = stripslashes(isset($matches[2]) ? $matches[2] : $matches[1]);
		} elseif (preg_match(self::SPLIT_PATTERN_VALUEMULTILINELITERAL, $unparsedValue, $matches) === 1) {
			$processedValue = stripslashes(isset($matches['SingleQuoteValue']) ? $matches['SingleQuoteValue'] : $matches['DoubleQuoteValue']);
			$closingQuoteChar = isset($matches['SingleQuoteChar']) ? $matches['SingleQuoteChar'] : $matches['DoubleQuoteChar'];
			$regexp = '/(?P<Value>(?:\\\\.|[^\\\\' . $closingQuoteChar . '])*)(?P<QuoteChar>' . $closingQuoteChar . '?)/';
			while(($typoScriptLine = $this->getNextTypoScriptLine()) !== FALSE) {
				preg_match($regexp, $typoScriptLine, $matches);
				$processedValue .= "\n" . stripslashes($matches['Value']);
				if (!empty($matches['QuoteChar'])) {
					break;
				}
			}
		} elseif (preg_match(self::SPLIT_PATTERN_VALUEBOOLEAN, $unparsedValue, $matches) === 1) {
			$processedValue = (strtolower($matches[1]) === 'true');
		} elseif (preg_match(self::SCAN_PATTERN_VALUEOBJECTTYPE, $unparsedValue, $matches) === 1) {
			if (empty($matches['namespace'])) {
				$objectTypeNamespace = $this->objectTypeNamespaces['default'];
			} else {
				$objectTypeNamespace = (isset($this->objectTypeNamespaces[$matches['namespace']])) ? $this->objectTypeNamespaces[$matches['namespace']] : $matches['namespace'];
			}
			$processedValue = array(
				'__objectType' => $objectTypeNamespace . ':' . $matches['unqualifiedType'],
				'__value' => NULL,
				'__eelExpression' => NULL
			);
		} else {
			throw new Exception('Syntax error: Invalid value "' . $unparsedValue . '" in value assignment.', 1180604192);
		}
		return $processedValue;
	}

	/**
	 * Assigns a value to a node or a property in the object tree, specified by the object path array.
	 *
	 * @param array $objectPathArray The object path, specifying the node / property to set
	 * @param mixed $value The value to assign, is a non-array type or an array with __eelExpression etc.
	 * @param array $objectTree The current (sub-) tree, used internally - don't specify!
	 * @return array The modified object tree
	 */
	protected function setValueInObjectTree(array $objectPathArray, $value, &$objectTree = NULL) {
		if ($objectTree === NULL) {
			$objectTree = &$this->objectTree;
		}

		$currentKey = array_shift($objectPathArray);
		if ((integer)$currentKey > 0) {
			$currentKey = (integer)$currentKey;
		}

		if (count($objectPathArray) === 0) {
			// last part of the iteration, setting the final value
			if (isset($objectTree[$currentKey]) && $value === NULL) {
				unset($objectTree[$currentKey]);
			} elseif (isset($objectTree[$currentKey]) && is_array($objectTree[$currentKey])) {
				if (is_array($value)) {
					$objectTree[$currentKey] = \TYPO3\Flow\Utility\Arrays::arrayMergeRecursiveOverrule($objectTree[$currentKey], $value);
				} else {
					$objectTree[$currentKey]['__value'] = $value;
					$objectTree[$currentKey]['__eelExpression'] = NULL;
					$objectTree[$currentKey]['__objectType'] = NULL;
				}
			} else {
				$objectTree[$currentKey] = $value;
			}
		} elseif (count($objectPathArray) >= 1) {
			// we still need to traverse further down
			if (isset($objectTree[$currentKey]) && !is_array($objectTree[$currentKey])) {
				// the element one-level-down is already defined, but it is NOT an array. So we need to convert the simple type to __value
				$objectTree[$currentKey] = array(
					'__value' => $objectTree[$currentKey],
					'__eelExpression' => NULL,
					'__objectType' => NULL
				);
			} elseif (!isset($objectTree[$currentKey])) {
				$objectTree[$currentKey] = array();
			}

			$this->setValueInObjectTree($objectPathArray, $value, $objectTree[$currentKey]);
		}

		return $objectTree;
	}

	/**
	 * Retrieves a value from a node in the object tree, specified by the object path array.
	 *
	 * @param array $objectPathArray The object path, specifying the node to retrieve the value of
	 * @param array $objectTree The current (sub-) tree, used internally - don't specify!
	 * @return mixed The value
	 */
	protected function &getValueFromObjectTree(array $objectPathArray, &$objectTree = NULL) {
		if (is_null($objectTree)) $objectTree = &$this->objectTree;

		if (count($objectPathArray) > 0) {
			$currentKey = array_shift($objectPathArray);
			if ((integer)$currentKey > 0) $currentKey = intval($currentKey);
			if (!isset($objectTree[$currentKey])) {
				$objectTree[$currentKey] = array();
			}
			$value = &$this->getValueFromObjectTree($objectPathArray, $objectTree[$currentKey]);
		} else {
			$value = &$objectTree;
		}
		return $value;
	}

	/**
	 * Returns the first part of an object path from the current object path stack
	 * which can be used to prefix a relative object path.
	 *
	 * @return string A part of an object path, ready to use as a prefix
	 */
	protected function getCurrentObjectPathPrefix() {
		return (count($this->currentObjectPathStack) > 0) ? $this->currentObjectPathStack[count($this->currentObjectPathStack) - 1] . '.' : '';
	}

	/**
	 * Precalculate merged configuration for inherited prototypes.
	 *
	 * @return void
	 */
	protected function buildPrototypeHierarchy() {
		if (!isset($this->objectTree['__prototypes'])) {
			return;
		}

		foreach ($this->objectTree['__prototypes'] as $prototypeName => $prototypeConfiguration) {
			$prototypeInheritanceHierarchy = array($prototypeName);
			$currentPrototypeName = $prototypeName;
			while (isset($this->objectTree['__prototypes'][$currentPrototypeName]['__prototypeObjectName'])) {
				$currentPrototypeName = $this->objectTree['__prototypes'][$currentPrototypeName]['__prototypeObjectName'];
				array_unshift($prototypeInheritanceHierarchy, $currentPrototypeName);
			}

			$flattenedPrototype = $this->flattenPrototypeHierarchy($prototypeInheritanceHierarchy);
			$this->objectTree['__prototypes'][$prototypeName] = $flattenedPrototype;
		}
	}

	/**
	 * Flattens the prototype inheritance hierarchy into a merged final prototype.
	 *
	 * @param array $prototypeInheritanceHierarchy
	 * @return array
	 */
	protected function flattenPrototypeHierarchy($prototypeInheritanceHierarchy) {
		$flattenedPrototype = array();
		foreach ($prototypeInheritanceHierarchy as $singlePrototypeName) {
			if (isset($this->objectTree['__prototypes'][$singlePrototypeName])) {
				$flattenedPrototype = Arrays::arrayMergeRecursiveOverrule($flattenedPrototype, $this->objectTree['__prototypes'][$singlePrototypeName]);
			}
		}

		return $flattenedPrototype;
	}
}
