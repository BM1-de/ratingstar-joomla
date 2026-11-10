<?php

/**
 * @package     RatingStar.Module
 * @subpackage  mod_ratingstar
 *
 * @copyright   (C) Baumgärtner Marketing GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace RatingStar\Module\Ratingstar\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/**
 * Dispatcher class for mod_ratingstar.
 *
 * @since  0.2.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data.
     *
     * @return  array
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $seal = $this->getHelperFactory()
            ->getHelper('RatingstarHelper')
            ->getSealData($data['params']);

        // A module may carry its own API key: the pages it is assigned to then
        // get the Google review stars of that profile instead of the site-wide
        // ones. The system plugin listens for this event and still prints one
        // block per page only. Without the plugin nobody listens.
        if ($seal->pageKey !== '') {
            $this->getApplication()->getDispatcher()->dispatch(
                'onRatingStarPageKey',
                new Event('onRatingStarPageKey', ['key' => $seal->pageKey, 'origin' => $seal->origin])
            );
        }

        $data['seal'] = $seal;

        return $data;
    }
}
