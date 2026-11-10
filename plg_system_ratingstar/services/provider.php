<?php

/**
 * @package     RatingStar.Plugin
 * @subpackage  System.ratingstar
 *
 * @copyright   (C) Baumgärtner Marketing GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Http\HttpFactory;
use RatingStar\Plugin\System\Ratingstar\Extension\Ratingstar;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Ratingstar::class, function (Container $container) {
                $plugin = new Ratingstar(
                    (array) PluginHelper::getPlugin('system', 'ratingstar'),
                    $container->get(CacheControllerFactoryInterface::class),
                    new HttpFactory()
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
