<?php

/**
 * @package     RatingStar.Module
 * @subpackage  mod_ratingstar
 *
 * @copyright   (C) Baumgärtner Marketing GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace RatingStar\Module\Ratingstar\Site\Helper;

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Helper for mod_ratingstar.
 *
 * Turns the module params into the data the layout prints: the official
 * `.rs-seal` embed (rendered by RatingStar's seal.js) or a linked static
 * image. Nothing is rendered or fetched here; every URL is built from the one
 * configured base origin.
 *
 * @since  0.2.0
 */
class RatingstarHelper
{
    /**
     * Default RatingStar origin, used when neither the module nor the system
     * plugin sets one.
     */
    public const DEFAULT_ORIGIN = 'https://ratingstar.de';

    /**
     * Variant used when the stored value is unknown — the same fallback
     * seal.js renders for an unknown data-variant.
     */
    public const DEFAULT_VARIANT = 'seal-circle-banner';

    /**
     * Canonical seal.js variant keys (SealConfig::VARIANT_META of the app).
     *
     * @var array<int, string>
     */
    public const VARIANTS = [
        // static (every plan)
        'seal-circle',
        'seal-circle-banner',
        // live (4-Star plan and up)
        'profile-card',
        'bar',
        'hero',
        'quote',
        'carousel',
        'wall',
        'footer-bar',
    ];

    /**
     * Legacy variant names (SealConfig::VARIANT_ALIAS of the app). Accepted
     * when they turn up in stored params, never offered in the form.
     *
     * @var array<string, string>
     */
    public const VARIANT_ALIAS = [
        'circle'   => 'seal-circle',
        'banner'   => 'seal-circle-banner',
        'card'     => 'profile-card',
        'floating' => 'profile-card',
        'pro'      => 'profile-card',
    ];

    /**
     * Variants that exist as a static SVG, mapped to the `?variant=` value of
     * the SVG endpoint. The static image mode is limited to these, so a live
     * widget is never silently swapped for a different motif.
     *
     * @var array<string, string>
     */
    public const STATIC_SVG_MAP = [
        'seal-circle'        => 'circle',
        'seal-circle-banner' => 'banner',
        'profile-card'       => 'card',
    ];

    /**
     * Values of data-position (profile card only).
     *
     * @var array<int, string>
     */
    public const POSITIONS = ['inline', 'bottom-right', 'bottom-left', 'top-right', 'top-left'];

    /**
     * Per-embed options offered per variant. Keys are the EMBED_OVERRIDES
     * keys of seal.js, grouped like the code generator in the RatingStar
     * portal; each becomes a `data-<kebab-case>` attribute.
     *
     * @var array<string, array<int, string>>
     */
    public const VARIANT_OPTIONS = [
        'seal-circle'        => ['size'],
        'seal-circle-banner' => ['size'],
        'profile-card'       => ['pcWidth', 'pcShowCount', 'pcColor', 'pcMobile', 'pcClick'],
        'bar'                => ['barShowCount', 'barShowVerified', 'theme'],
        'hero'               => ['heroShowCount', 'heroShowVerified', 'heroShowBranches', 'theme'],
        'quote'              => ['quotePick', 'quoteWidth', 'reviewsOnlyNamed', 'reviewsOnlyWithComment', 'theme'],
        'carousel'           => ['carCount', 'carRotate', 'carMin', 'carWidth', 'reviewsOnlyNamed', 'reviewsOnlyWithComment', 'theme'],
        'wall'               => ['wallCols', 'wallTotal', 'wallMin', 'wallSort', 'wallWidth', 'reviewsOnlyNamed', 'reviewsOnlyWithComment', 'theme'],
        'footer-bar'         => ['footerBarPosition', 'footerBarBg', 'footerBarText', 'footerBarLinkColor', 'footerBarLinkHover'],
    ];

    /**
     * Value type per option. Only the shape is checked here; ranges and enum
     * values are enforced by seal.js, which falls back to the portal setting
     * for anything invalid.
     *
     * @var array<string, string>
     */
    private const OPTION_TYPES = [
        'size'                   => 'int',
        'pcWidth'                => 'int',
        'pcShowCount'            => 'bool',
        'pcColor'                => 'enum',
        'pcMobile'               => 'enum',
        'pcClick'                => 'enum',
        'barShowCount'           => 'bool',
        'barShowVerified'        => 'bool',
        'heroShowCount'          => 'bool',
        'heroShowVerified'       => 'bool',
        'heroShowBranches'       => 'bool',
        'quotePick'              => 'enum',
        'quoteWidth'             => 'enum',
        'carCount'               => 'int',
        'carRotate'              => 'int',
        'carMin'                 => 'int',
        'carWidth'               => 'enum',
        'wallCols'               => 'int',
        'wallTotal'              => 'int',
        'wallMin'                => 'int',
        'wallSort'               => 'enum',
        'wallWidth'              => 'enum',
        'footerBarPosition'      => 'enum',
        'footerBarBg'            => 'color',
        'footerBarText'          => 'color',
        'footerBarLinkColor'     => 'color',
        'footerBarLinkHover'     => 'color',
        'reviewsOnlyNamed'       => 'bool',
        'reviewsOnlyWithComment' => 'bool',
        'theme'                  => 'enum',
    ];

    /**
     * Attributes the free-text field must not set: identity, variant,
     * position and the rich-snippet switch come from their own fields, the
     * preview attributes belong to the RatingStar portal alone.
     *
     * @var array<int, string>
     */
    public const RESERVED_ATTRIBUTES = [
        'data-slug',
        'data-group',
        'data-variant',
        'data-position',
        'data-no-richsnippet',
        'data-preview-config',
        'data-preview-data',
        'data-preview-svg',
    ];

    /**
     * Assembles the data the layout needs to print the seal.
     *
     * @param   Registry  $params  The module params.
     *
     * @return  \stdClass  mode (live|static|none), origin, pageKey, attributes,
     *                     scriptUrl, imgUrl, linkUrl, alt, notice.
     */
    public function getSealData(Registry $params): \stdClass
    {
        $site = $this->getSiteSettings();

        $origin = self::normalizeOrigin((string) $params->get('base_origin', ''))
            ?: self::normalizeOrigin((string) ($site ? $site->get('base_origin', '') : ''))
            ?: self::DEFAULT_ORIGIN;

        $seal = (object) [
            'mode'       => 'none',
            'origin'     => $origin,
            'pageKey'    => self::normalizeKey((string) $params->get('api_key', '')),
            'attributes' => [],
            'scriptUrl'  => '',
            'imgUrl'     => '',
            'linkUrl'    => '',
            'alt'        => 'MOD_RATINGSTAR_SEAL_ALT',
            'notice'     => '',
        ];

        [$isGroup, $handle] = $this->resolveSubject($params, $site);

        if ($handle === '') {
            $seal->notice = 'MOD_RATINGSTAR_NO_SLUG';

            return $seal;
        }

        $variant = $this->resolveVariant((string) $params->get('variant', self::DEFAULT_VARIANT));

        // Static image: a linked <img> of the SVG, no JavaScript at all.
        if ($params->get('render_mode', 'live') === 'static' && isset(self::STATIC_SVG_MAP[$variant])) {
            $seal->linkUrl = $origin . ($isGroup ? '/g/' : '/t/') . rawurlencode($handle);

            if ($params->get('static_motif', 'seal') === 'award') {
                $award = trim((string) $params->get('award_slug', ''));

                // Award badges exist per branch only; the slug shape is the
                // one the app route accepts (<type>-<level>).
                if ($isGroup || preg_match('/^[a-z_]+-\d+$/', $award) !== 1) {
                    $seal->notice = 'MOD_RATINGSTAR_AWARD_UNAVAILABLE';

                    return $seal;
                }

                $seal->imgUrl = $origin . '/seal/' . rawurlencode($handle) . '/award/' . $award . '.svg';
                $seal->alt    = 'MOD_RATINGSTAR_AWARD_ALT';
            } else {
                $seal->imgUrl = $origin . '/seal/' . ($isGroup ? 'g/' : '') . rawurlencode($handle)
                    . '.svg?variant=' . self::STATIC_SVG_MAP[$variant];
            }

            $seal->mode = 'static';

            return $seal;
        }

        $attributes = [
            'class'                                => 'rs-seal',
            ($isGroup ? 'data-group' : 'data-slug') => $handle,
            'data-variant'                         => $variant,
        ];

        // data-position only means something on the profile card.
        $position = (string) $params->get('position', '');

        if ($variant === 'profile-card' && \in_array($position, self::POSITIONS, true)) {
            $attributes['data-position'] = $position;
        }

        // Single options first, then the free-text attributes on top.
        $attributes = array_replace(
            $attributes,
            $this->collectOptions($params, $variant, $isGroup),
            self::parseDataAttributes((string) $params->get('data_attributes', ''))
        );

        // One JSON-LD block per page: once the system plugin prints the
        // Google review stars server-side, seal.js must not add its own.
        if ((bool) $params->get('no_richsnippet', 0) || $this->serverJsonLdActive($site, $seal->pageKey)) {
            $attributes['data-no-richsnippet'] = '1';
        }

        $seal->mode       = 'live';
        $seal->attributes = $attributes;
        $seal->scriptUrl  = $origin . '/seal.js';

        return $seal;
    }

    /**
     * Site-wide settings kept in the system plugin, or null when the plugin
     * is not enabled.
     *
     * @return  Registry|null
     */
    protected function getSiteSettings(): ?Registry
    {
        $plugin = PluginHelper::getPlugin('system', 'ratingstar');

        if (!\is_object($plugin)) {
            return null;
        }

        return new Registry($plugin->params ?? '');
    }

    /**
     * Picks the profile the seal shows: the group or branch set on the
     * module, otherwise the site-wide one. A group always wins over a branch
     * on the same level — an embed never carries both.
     *
     * @param   Registry       $params  The module params.
     * @param   Registry|null  $site    The site-wide settings.
     *
     * @return  array{0: bool, 1: string}  [is group, handle]
     */
    private function resolveSubject(Registry $params, ?Registry $site): array
    {
        foreach ([$params, $site] as $source) {
            if ($source === null) {
                continue;
            }

            $group = self::cleanHandle((string) $source->get('group', ''));

            if ($group !== '') {
                return [true, $group];
            }

            $slug = self::cleanHandle((string) $source->get('slug', ''));

            if ($slug !== '') {
                return [false, $slug];
            }
        }

        return [false, ''];
    }

    /**
     * Maps a stored variant onto a canonical key.
     *
     * @param   string  $variant  The stored variant.
     *
     * @return  string
     */
    private function resolveVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));
        $variant = self::VARIANT_ALIAS[$variant] ?? $variant;

        return \in_array($variant, self::VARIANTS, true) ? $variant : self::DEFAULT_VARIANT;
    }

    /**
     * Collects the single options of the active variant as data attributes.
     * An empty value means "default (portal)" and yields no attribute, so the
     * settings made in RatingStar are never overruled by a form default.
     *
     * @param   Registry  $params   The module params.
     * @param   string    $variant  The canonical variant.
     * @param   bool      $isGroup  Whether the seal shows a group.
     *
     * @return  array<string, string>
     */
    private function collectOptions(Registry $params, string $variant, bool $isGroup): array
    {
        $attributes = [];

        foreach (self::VARIANT_OPTIONS[$variant] ?? [] as $key) {
            // The rating per branch only exists on group seals.
            if ($key === 'heroShowBranches' && !$isGroup) {
                continue;
            }

            $value = trim((string) $params->get($key, ''));

            if ($value === '') {
                continue;
            }

            switch (self::OPTION_TYPES[$key]) {
                case 'bool':
                    $valid = $value === '0' || $value === '1';
                    break;

                case 'int':
                    $valid = ctype_digit($value);
                    break;

                case 'color':
                    // seal.js takes six-digit hex colours only.
                    if (preg_match('/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $value, $m) === 1) {
                        $value = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
                    }

                    $valid = preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
                    break;

                default:
                    $valid = preg_match('/^[a-z0-9-]+$/', $value) === 1;
            }

            if ($valid) {
                $attributes[self::attributeName($key)] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Whether the system plugin prints the JSON-LD for this page: it is
     * enabled, the output is switched on and a valid key is available
     * (site-wide or from this module).
     *
     * @param   Registry|null  $site     The site-wide settings.
     * @param   string         $pageKey  The valid key of this module, or ''.
     *
     * @return  bool
     */
    private function serverJsonLdActive(?Registry $site, string $pageKey): bool
    {
        if ($site === null || !(int) $site->get('jsonld', 1)) {
            return false;
        }

        return $pageKey !== '' || self::normalizeKey((string) $site->get('api_key', '')) !== '';
    }

    /**
     * Splits a raw attribute string (data-size="240" data-pc-color='weiss'
     * data-car-count=4, or a whole pasted embed code) into data attributes.
     * Only data-* names survive, reserved ones are dropped; the values are
     * escaped on output and validated by seal.js.
     *
     * @param   string  $raw  The free-text field.
     *
     * @return  array<string, string>
     */
    public static function parseDataAttributes(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        preg_match_all(
            '/(?<![\w-])(data-[a-z][a-z0-9-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i',
            $raw,
            $matches,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
        );

        $attributes = [];

        foreach ($matches as $match) {
            $name = strtolower($match[1]);

            if (\in_array($name, self::RESERVED_ATTRIBUTES, true)) {
                continue;
            }

            $value = $match[2] ?? $match[3] ?? $match[4] ?? '';

            $attributes[$name] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $attributes;
    }

    /**
     * camelCase option key → data attribute name (pcColor → data-pc-color).
     *
     * @param   string  $key  The option key.
     *
     * @return  string
     */
    private static function attributeName(string $key): string
    {
        return 'data-' . strtolower((string) preg_replace('/[A-Z]/', '-$0', $key));
    }

    /**
     * Returns a profile or group slug if it only holds URL-safe characters,
     * otherwise ''.
     *
     * @param   string  $handle  The configured slug.
     *
     * @return  string
     */
    private static function cleanHandle(string $handle): string
    {
        $handle = trim($handle);

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $handle) === 1 ? $handle : '';
    }

    /**
     * Returns the API key if it has the `rs_live_` + 40 characters form,
     * otherwise ''.
     *
     * @param   string  $key  The configured key.
     *
     * @return  string
     */
    private static function normalizeKey(string $key): string
    {
        $key = trim($key);

        return preg_match('/^rs_live_[A-Za-z0-9]{40}$/', $key) === 1 ? $key : '';
    }

    /**
     * Reduces a configured base URL to its origin (scheme://host[:port]).
     * Returns '' for anything that is not an http(s) URL, so the caller can
     * fall back to the next source.
     *
     * @param   string  $origin  The configured origin.
     *
     * @return  string
     */
    private static function normalizeOrigin(string $origin): string
    {
        $parts = parse_url(trim($origin));

        if (!\is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        return $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
    }
}
