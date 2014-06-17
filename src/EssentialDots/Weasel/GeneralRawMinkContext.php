<?php
namespace EssentialDots\Weasel;

use Behat\Behat\Context\ClosuredContextInterface,
	Behat\Behat\Context\TranslatedContextInterface,
	Behat\Behat\Context\BehatContext,
	Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;

use  Behat\Mink\Exception\ElementNotFoundException,
	Behat\Mink\Exception\ExpectationException,
	Behat\Mink\Exception\ResponseTextException,
	Behat\Mink\Exception\ElementHtmlException,
	Behat\Mink\Exception\ElementTextException;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Essential Dots d.o.o. Belgrade
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

class GeneralRawMinkContext extends \Behat\MinkExtension\Context\RawMinkContext {

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var boolean
	 */
	protected $isInitialized = FALSE;

	/**
	 * @var string
	 */
	protected $environment;

	/**
	 * @var array
	 */
	protected $parameters;

	/**
	 * constructor
	 */
	public function __construct(array $parameters) {
		if ($parameters && is_array($parameters)) {
			$this->parameters = $parameters;
		} else {
			$this->parameters = array();
		}
	}

	/**
	 * Include syn.js
	 */
	protected function withSynJS() {
		$hasSynJS = $this->getSession()->evaluateScript('return typeof window["Syn"]!=="undefined"');

		if (!$hasSynJS) {
			$synJS = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'JavaScript' . DIRECTORY_SEPARATOR . 'syn.js');
			$this->getSession()->executeScript($synJS);
		}

		return $this;
	}

	/**
	 * Returns Mink session.
	 *
	 * @param string|null $name name of the session OR active session will be used
	 *
	 * @return \Behat\Mink\Session
	 */
	public function getSession($name = null) {
		$session = $this->getMink()->getSession($name);

		if (!$this->isInitialized) {
			$this->initializeSettings($session);
		}

		return $session;
	}

	/**
	 * @throws \Behat\Behat\Exception\PendingException
	 */
	protected function initializeSettings(\Behat\Mink\Session $session) {

	}

	/**
	 * Locates url, based on provided path.
	 * Override to provide custom routing mechanism.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public function locatePath($path) {
		$base_urls = $this->getMinkParameter('base_urls');
		$base_urls_context = array_key_exists('base_urls', $this->parameters) ? $this->parameters['base_urls'] : array();
		$base_urls = self::arrayMergeRecursiveOverrule($base_urls, $base_urls_context);

		$base_url = $this->getMinkParameter('base_url');
		$base_url = array_key_exists('base_url', $this->parameters) ? $this->parameters['base_url'] : $base_url;

		$environment = $this->environment ? $this->environment : 'default';
		$base_url = array_key_exists($environment, $base_urls) ? $base_urls[$environment] : $base_url;

		$startUrl = rtrim($base_url, '/') . '/';

		return 0 !== strpos($path, 'http') ? $startUrl . ltrim($path, '/') : $path;
	}

	/**
	 * Waits some time or until JS condition turns true.
	 * Throws exception if condition is not satisfied withing given $time
	 *
	 * @param integer $time time in milliseconds
	 * @param string $condition JS condition
	 * @param string $errorMessage
	 * @throws \Behat\Mink\Exception\ExpectationException
	 */
	public function assertJSCondition($time, $condition = 'false', $errorMessage) {

		$this->getSession()->wait($time, $condition);

		if (!$this->getSession()->evaluateScript("return $condition;")) {
			$message = sprintf('%s after %sms timeout, condition "%s" has not been satisfied.', $errorMessage, $time, $condition);
			throw new ExpectationException($message, $this->getSession());
		}
	}

	/**
	 * @param int $time
	 * @param string $fullLocator
	 * @return \Behat\Mink\Element\NodeElement|null
	 * @throws \Behat\Mink\Exception\ExpectationException
	 */
	public function waitForElement($time, $fullLocator) {
		$page = $this->getSession()->getPage();
		list($selector, $locator) = $this->expandLocatorDefintion($fullLocator);
		$start = 1000 * microtime(true);
		$end = $start + $time;

		$element = $page->find($selector, $locator);
		while (1000 * microtime(true) < $end && $element === null) {
			sleep(0.1);
			$element = $page->find($selector, $locator);
		}

		if ($element === null) {
			$message = sprintf('Element %s has not been found after %sms timeout.', $locator, $time);
			throw new ExpectationException($message, $this->getSession());
		}

		return $element;
	}

	/**
	 * @param int $time
	 * @param string $fullLocator
	 * @return void
	 * @throws \Behat\Mink\Exception\ExpectationException
	 */
	public function waitForElementToDisappear($time, $fullLocator) {
		$page = $this->getSession()->getPage();
		list($selector, $locator) = $this->expandLocatorDefintion($fullLocator);
		$start = 1000 * microtime(true);
		$end = $start + $time;

		$element = $page->find($selector, $locator);
		while (1000 * microtime(true) < $end && $element !== null) {
			sleep(0.1);
			$element = $page->find($selector, $locator);
		}

		if ($element !== null) {
			$message = sprintf('Element %s has not disappeared after %sms timeout.', $locator, $time);
			throw new ExpectationException($message, $this->getSession());
		}
	}

	/**
	 * @param $locator
	 * @return array
	 */
	public function expandLocatorDefintion($locator) {
		if (substr($locator, 0, 6) == 'xpath:') {
			return array('xpath', substr($locator, 6));
		} elseif (substr($locator, 0, 4) == 'css:') {
			return array('css', substr($locator, 4));
		} else {
			return array('named', $locator);
		}
	}

	/**
	 * Merges two arrays recursively and "binary safe" (integer keys are
	 * overridden as well), overruling similar values in the first array
	 * ($arr0) with the values of the second array ($arr1)
	 * In case of identical keys, ie. keeping the values of the second.
	 *
	 * @param array $arr0 First array
	 * @param array $arr1 Second array, overruling the first array
	 * @param boolean $notAddKeys If set, keys that are NOT found in $arr0 (first array) will not be set. Thus only existing value can/will be overruled from second array.
	 * @param boolean $includeEmptyValues If set, values from $arr1 will overrule if they are empty or zero. Default: TRUE
	 * @return array Resulting array where $arr1 values has overruled $arr0 values
	 */
	public static function arrayMergeRecursiveOverrule(array $arr0, array $arr1, $notAddKeys = FALSE, $includeEmptyValues = TRUE) {
		foreach ($arr1 as $key => $val) {
			if (is_array($arr0[$key])) {
				if (is_array($arr1[$key])) {
					$arr0[$key] = self::arrayMergeRecursiveOverrule($arr0[$key], $arr1[$key], $notAddKeys, $includeEmptyValues);
				}
			} else {
				if ($notAddKeys) {
					if (isset($arr0[$key])) {
						if ($includeEmptyValues || $val) {
							$arr0[$key] = $val;
						}
					}
				} else {
					if ($includeEmptyValues || $val) {
						$arr0[$key] = $val;
					}
				}
			}
		}
		reset($arr0);
		return $arr0;
	}


	/**
	 * Converts a csv file into an array of lines and columns.
	 *
	 * @param $fileContent String
	 * @param string $escape String
	 * @param string $enclosure String
	 * @param string $delimiter String
	 * @return array
	 */
	public static function strGetCSV($fileContent, $escape = '\\', $enclosure = '"', $delimiter = ',') {
		$lines = array();
		$fields = array();

		if ($escape == $enclosure) {
			$escape = '\\';
			$fileContent = str_replace(array('\\', $enclosure . $enclosure, "\r\n", "\r"),
				array('\\\\', $escape . $enclosure, "\\n", "\\n"), $fileContent);
		} else {
			$fileContent = str_replace(array("\r\n", "\r"), array("\\n", "\\n"), $fileContent);
		}

		$nb = strlen($fileContent);
		$field = '';
		$inEnclosure = false;
		$previous = '';

		for ($i = 0; $i < $nb; $i++) {
			$c = $fileContent[$i];
			if ($c === $enclosure) {
				if ($previous !== $escape) {
					$inEnclosure ^= true;
					//$field .= $enclosure;
				} else {
					$field .= $enclosure;
				}
			} elseif ($c === $escape) {
				$next = $fileContent[$i + 1];
				if ($next != $enclosure && $next != $escape) {
					$field .= $escape;
				}
			} elseif ($c === $delimiter) {
				if ($inEnclosure) {
					$field .= $delimiter;
				} else {
					//end of the field
					$fields[] = trim($field);
					$field = '';
				}
			} elseif ($c === "\n") {
				$fields[] = trim($field);
				$field = '';
				$lines[] = $fields;
				$fields = array();
			} else {
				$field .= $c;
			}
			$previous = $c;
		}
		//we add the last element
		if (true || $field !== '') {
			$fields[] = trim($field);
			$lines[] = $fields;
		}
		return $lines;
	}
}
