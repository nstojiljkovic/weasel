<?php

namespace EssentialDots\Weasel;

use Symfony\Component\Config\FileLocator,
	Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition,
	Symfony\Component\DependencyInjection\ContainerBuilder,
	Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use Behat\Behat\Extension\ExtensionInterface;

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

/**
 * Overload the default Mink extension class.
 */
class Extension extends \Behat\MinkExtension\Extension {

	/**
	 * Loads a specific configuration.
	 *
	 * @param array $config
	 * @param ContainerBuilder $container
	 * @throws \RuntimeException
	 */
	public function load(array $config, ContainerBuilder $container) {
		parent::load($config, $container);

		$loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/services'));
		if (isset($config['webkit'])) {
			if (false && !class_exists('EssentialDots\\Mink\\Driver\\WebkitDriver')) {
				$webkitDriverFolder = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/mink-capybara-webkit-driver/src/EssentialDots/Mink/Driver';
				if (file_exists($webkitDriverFolder . '/WebkitDriver.php') && is_file($webkitDriverFolder . '/WebkitDriver.php') &&
					file_exists($webkitDriverFolder . '/Webkit/Browser.php') && is_file($webkitDriverFolder . '/Webkit/Browser.php')
				) {
					//
					// enable usage of driver before this gets public
					//
					require_once($webkitDriverFolder . '/WebkitDriver.php');
					require_once($webkitDriverFolder . '/Webkit/Browser.php');
				} else {
					throw new \RuntimeException(
						'Install Capybara Webkit Driver in order to activate webkit session.'
					);
				}
			}

			$loader->load('sessions/webkit.xml');
		}

		$minkParameters = $container->getParameter('behat.mink.parameters');

		foreach ($config as $ns => $tlValue) {
			if ($ns == "webkit") {
				if (is_array($tlValue)) {
					foreach ($tlValue as $name => $value) {
						$container->setParameter("behat.mink.$ns.$name", $value);
					}
				}
			}
			if ($ns == "base_urls") {
				if (is_array($tlValue)) {
					$minkParameters[$ns] = array();

					foreach ($tlValue as $name => $value) {
						$minkParameters[$ns][$name] = $value;
					}
				}
			}
		}

		$container->setParameter('behat.mink.parameters', $minkParameters);
	}

	/**
	 * Setups configuration for current extension.
	 *
	 * @param ArrayNodeDefinition $builder
	 */
	public function getConfig(ArrayNodeDefinition $builder) {
		parent::getConfig($builder);

		$node = new ArrayNodeDefinition('webkit');

		$node->
			children()->
				scalarNode('bin_path')->
					defaultValue('/usr/lib/ruby/gems/1.8/gems/capybara-webkit-0.14.1/bin/webkit_server')->
				end()->
				scalarNode('ignore_ssl_errors')->
					defaultValue(false)->
				end()->
			end()->
		end();

		$builder->children()->append($node);

		$node = new ArrayNodeDefinition('base_urls');

		$node->
			useAttributeAsKey('key')->
				prototype('variable')->end()->
			end();

		$builder->children()->append($node);

	}
}

return new Extension();