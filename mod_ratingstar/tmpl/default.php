<?php

/**
 * @package     RatingStar.Module
 * @subpackage  mod_ratingstar
 *
 * @copyright   (C) Baumgärtner Marketing GmbH
 * @license     proprietary
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

// Without a slug there is nothing to embed. Show a hint to back-end admins,
// stay silent for visitors.
if ($seal->slug === '') {
    $user = $app->getIdentity();

    if ($user && $user->authorise('core.admin')) {
        echo '<div class="alert alert-warning" role="alert">'
            . Text::_('MOD_RATINGSTAR_NO_SLUG')
            . '</div>';
    }

    return;
}

// Static-image fallback (CONTRACT.md §12): a plain <img> of the SVG / award
// endpoint, no seal.js and no third-party JS at all — for email/PDF/AMP or
// JS-off contexts.
if ($seal->renderMode === 'static') {
    if ($seal->imgUrl === '') {
        return;
    }

    echo '<img class="rs-seal-static" src="'
        . htmlspecialchars($seal->imgUrl, ENT_QUOTES, 'UTF-8')
        . '" alt="' . htmlspecialchars(Text::_('MOD_RATINGSTAR_SEAL_ALT'), ENT_QUOTES, 'UTF-8')
        . '" loading="lazy" decoding="async" />';

    return;
}

// Load seal.js once per page. The WebAssetManager dedupes by asset name, so
// multiple seal modules on one page still emit a single async <script>
// (CONTRACT.md §4). seal.js derives the RatingStar origin from its own src.
$wa = $app->getDocument()->getWebAssetManager();

if (!$wa->assetExists('script', 'mod_ratingstar.seal')) {
    $wa->registerScript('mod_ratingstar.seal', $seal->scriptUrl, [], ['async' => true]);
}

$wa->useScript('mod_ratingstar.seal');

// Official bind markup — every attribute value HTML-escaped (CONTRACT.md §4).
$attributes = [
    'class="rs-seal"',
    'data-slug="' . htmlspecialchars($seal->slug, ENT_QUOTES, 'UTF-8') . '"',
    'data-variant="' . htmlspecialchars($seal->variant, ENT_QUOTES, 'UTF-8') . '"',
];

if ($seal->position !== '') {
    $attributes[] = 'data-position="' . htmlspecialchars($seal->position, ENT_QUOTES, 'UTF-8') . '"';
}

if ($seal->theme !== '') {
    $attributes[] = 'data-theme="' . htmlspecialchars($seal->theme, ENT_QUOTES, 'UTF-8') . '"';
}

if ($seal->noRichsnippet) {
    $attributes[] = 'data-no-richsnippet="1"';
}

?>
<div <?php echo implode(' ', $attributes); ?>></div>
