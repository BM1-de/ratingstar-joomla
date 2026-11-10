<?php

/**
 * @package     RatingStar.Module
 * @subpackage  mod_ratingstar
 *
 * @copyright   (C) Baumgärtner Marketing GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Layout variables provided by the dispatcher:
 *
 * @var \Joomla\CMS\Application\SiteApplication $app
 * @var \Joomla\Registry\Registry              $params
 * @var \stdClass                              $module
 * @var \stdClass                              $seal
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// Nothing to show (no profile configured, award not available): tell
// administrators why, stay silent for visitors.
if ($seal->mode === 'none') {
    $user = $app->getIdentity();

    if ($seal->notice !== '' && $user && $user->authorise('core.admin')) {
        echo '<div class="alert alert-warning" role="alert">' . Text::_($seal->notice) . '</div>';
    }

    return;
}

// Static image: a linked <img> of the seal SVG, without seal.js or any other
// script — for contexts where JavaScript is not available.
if ($seal->mode === 'static') {
    echo '<a class="rs-seal-static-link" href="' . $escape($seal->linkUrl) . '" target="_blank" rel="noopener">'
        . '<img class="rs-seal-static" src="' . $escape($seal->imgUrl) . '" alt="'
        . $escape(Text::_($seal->alt)) . '" loading="lazy" decoding="async"></a>';

    return;
}

// seal.js once per page: the asset name dedupes it across all seal modules,
// and it scans every .rs-seal node itself. No version query, so the URL stays
// exactly <origin>/seal.js.
$wa = $app->getDocument()->getWebAssetManager();

if (!$wa->assetExists('script', 'mod_ratingstar.seal')) {
    $wa->registerScript('mod_ratingstar.seal', $seal->scriptUrl, ['version' => ''], ['async' => true]);
}

$wa->useScript('mod_ratingstar.seal');

// The official embed markup; every attribute value is escaped.
$attributes = '';

foreach ($seal->attributes as $name => $value) {
    $attributes .= ' ' . $name . '="' . $escape((string) $value) . '"';
}

?>
<div<?php echo $attributes; ?>></div>
