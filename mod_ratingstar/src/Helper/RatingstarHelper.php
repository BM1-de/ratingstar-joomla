<?php

/**
 * @package     RatingStar.Module
 * @subpackage  mod_ratingstar
 *
 * @copyright   (C) Baumgärtner Marketing GmbH
 * @license     proprietary
 */

namespace Joomla\Module\Ratingstar\Site\Helper;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Helper for mod_ratingstar.
 *
 * Builds the seal-embed data (slug, origin, variant, position) from the
 * module params. The actual rendering is done by RatingStar's seal.js, which
 * the template loads from the configured origin — this helper only assembles
 * the official `.rs-seal` markup contract (see CONTRACT.md §4/§5).
 *
 * @since  0.2.0
 */
class RatingstarHelper
{
    /**
     * Default RatingStar origin. Every endpoint URL is derived from this one
     * setting (CONTRACT.md §2) — nothing is hardcoded elsewhere.
     */
    public const DEFAULT_ORIGIN = 'https://ratingstar.de';

    /**
     * Canonical seal.js variant keys (SealConfig::VARIANT_META). The full set
     * must be offered identically across all plugins (CONTRACT.md §5).
     *
     * @var array<int, string>
     */
    public const VARIANTS = [
        // static
        'seal-circle',
        'seal-circle-banner',
        // live (tier 4-Star+)
        'profile-card',
        'bar',
        'floating',
        'hero',
        'quote',
        'carousel',
        'wall',
        'footer-bar',
    ];

    /**
     * Variants for which a `data-position` override is meaningful
     * (CONTRACT.md §4).
     *
     * @var array<int, string>
     */
    public const POSITION_VARIANTS = ['floating', 'footer-bar'];

    /**
     * Assembles the data the template needs to render the seal embed.
     *
     * @param   Registry  $params  The module params.
     *
     * @return  \stdClass
     */
    public function getSealData(Registry $params): \stdClass
    {
        $slug   = trim((string) $params->get('slug', ''));
        $origin = $this->normalizeOrigin((string) $params->get('base_origin', self::DEFAULT_ORIGIN));

        $variant = (string) $params->get('variant', 'seal-circle');

        if (!\in_array($variant, self::VARIANTS, true)) {
            $variant = 'seal-circle';
        }

        // data-position only applies to floating / footer-bar; drop it otherwise.
        $position = (string) $params->get('position', '');

        if (!\in_array($variant, self::POSITION_VARIANTS, true)) {
            $position = '';
        }

        return (object) [
            'slug'          => $slug,
            'origin'        => $origin,
            'variant'       => $variant,
            'position'      => $position,
            'noRichsnippet' => (bool) $params->get('no_richsnippet', 0),
            'scriptUrl'     => $origin . '/seal.js',
        ];
    }

    /**
     * Normalizes a base origin: trims whitespace and a trailing slash, falls
     * back to the default when empty.
     *
     * @param   string  $origin  The configured origin.
     *
     * @return  string
     */
    private function normalizeOrigin(string $origin): string
    {
        $origin = trim($origin);

        if ($origin === '') {
            return self::DEFAULT_ORIGIN;
        }

        return rtrim($origin, '/');
    }
}
