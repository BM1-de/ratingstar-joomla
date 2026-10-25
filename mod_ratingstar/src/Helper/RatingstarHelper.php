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
        'hero',
        'quote',
        'carousel',
        'wall',
        'footer-bar',
    ];

    /**
     * Variants for which a `data-position` override is meaningful
     * (CONTRACT.md §4): only the profile card, inline or in a screen corner
     * (it absorbed the former floating badge).
     *
     * @var array<int, string>
     */
    public const POSITION_VARIANTS = ['profile-card'];

    /**
     * Variants with a dark look via `data-theme` (CONTRACT.md §17).
     *
     * @var array<int, string>
     */
    public const THEME_VARIANTS = ['bar', 'hero', 'quote', 'carousel', 'wall'];

    /**
     * Variants for the static-image fallback (CONTRACT.md §12) — the
     * `/seal/<slug>.svg?variant=` endpoint only knows banner|circle|card.
     *
     * @var array<int, string>
     */
    public const STATIC_VARIANTS = ['banner', 'circle', 'card'];

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

        // Modules saved before the merge keep working as the profile card.
        if ($variant === 'floating') {
            $variant = 'profile-card';
        }

        if (!\in_array($variant, self::VARIANTS, true)) {
            $variant = 'seal-circle';
        }

        // data-position only applies to the profile card; drop it otherwise.
        $position = (string) $params->get('position', '');

        if (!\in_array($variant, self::POSITION_VARIANTS, true)) {
            $position = '';
        }

        // Empty = the colour scheme from the RatingStar settings.
        $theme = (string) $params->get('theme', '');

        if (!\in_array($variant, self::THEME_VARIANTS, true) || !\in_array($theme, ['light', 'dark'], true)) {
            $theme = '';
        }

        // Static-image fallback (CONTRACT.md §12): a plain <img> of the SVG /
        // award endpoint, no seal.js. For email/PDF/AMP or JS-off contexts.
        $renderMode    = $params->get('render_mode', 'live') === 'static' ? 'static' : 'live';
        $staticVariant = (string) $params->get('static_variant', 'banner');
        $awardSlug     = trim((string) $params->get('award_slug', ''));
        $imgUrl        = '';

        if ($renderMode === 'static' && $slug !== '') {
            $imgUrl = $this->buildStaticImageUrl($origin, $slug, $staticVariant, $awardSlug);
        }

        return (object) [
            'slug'          => $slug,
            'origin'        => $origin,
            'variant'       => $variant,
            'position'      => $position,
            'theme'         => $theme,
            'noRichsnippet' => (bool) $params->get('no_richsnippet', 0),
            'scriptUrl'     => $origin . '/seal.js',
            'renderMode'    => $renderMode,
            'imgUrl'        => $imgUrl,
        ];
    }

    /**
     * Builds the static-image URL for the fallback mode (CONTRACT.md §12).
     * banner|circle|card → the seal SVG; `award` → the award-badge SVG (only
     * when the award slug matches the `<type>-<level>` form).
     *
     * @param   string  $origin         The base origin.
     * @param   string  $slug           The profile slug.
     * @param   string  $staticVariant  banner|circle|card|award.
     * @param   string  $awardSlug      The award slug (for the award variant).
     *
     * @return  string  The image URL, or '' when it cannot be built.
     */
    private function buildStaticImageUrl(string $origin, string $slug, string $staticVariant, string $awardSlug): string
    {
        $base = $origin . '/seal/' . rawurlencode($slug);

        if ($staticVariant === 'award') {
            // Award slug shape enforced by the app route: [a-z_]+-<digits>.
            if (preg_match('/^[a-z_]+-\d+$/', $awardSlug) !== 1) {
                return '';
            }

            return $base . '/award/' . $awardSlug . '.svg';
        }

        if (!\in_array($staticVariant, self::STATIC_VARIANTS, true)) {
            $staticVariant = 'banner';
        }

        return $base . '.svg?variant=' . $staticVariant;
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
