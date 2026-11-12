<?php

/**
 * @package     RatingStar.Plugin
 * @subpackage  System.ratingstar
 *
 * @copyright   (C) Baumgärtner Marketing GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace RatingStar\Plugin\System\Ratingstar\Extension;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\OutputController;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Application\BeforeCompileHeadEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;

\defined('_JEXEC') or die;

/**
 * RatingStar system plugin.
 *
 * - Holds the site-wide settings (profile, group, API key, base origin) the
 *   seal modules fall back to.
 * - Prints the Google review stars server-side: the ready-made JSON-LD of
 *   the key-based endpoint (`<origin>/seal/k/<key>.jsonld?schema=product`),
 *   cached, validated and re-encoded, as exactly one block per page. The
 *   schema.org type (Product or LocalBusiness) is set in the RatingStar
 *   backend.
 * - Checks a profile, group or key against RatingStar when a seal module or
 *   this plugin is saved — as a hint only, the value is always kept.
 *
 * Nothing here may break the host page: every remote failure and every cache
 * failure ends in "no output".
 *
 * @since  0.2.0
 */
final class Ratingstar extends CMSPlugin implements SubscriberInterface
{
    /**
     * Default RatingStar origin. Every URL is built from the configured base
     * origin; this is only its default.
     */
    public const DEFAULT_ORIGIN = 'https://ratingstar.de';

    /**
     * Cache group of the fetched JSON-LD (System → Clear Cache lists it).
     */
    private const CACHE_GROUP = 'plg_system_ratingstar';

    /**
     * Log category and log file (in the log folder of the site) for cache
     * failures.
     */
    private const LOG_CATEGORY = 'plg_system_ratingstar';
    private const LOG_FILE     = 'plg_system_ratingstar.php';

    /**
     * Default lifetime of a fetched JSON-LD in minutes (6 hours).
     */
    private const CACHE_MINUTES = 360;

    /**
     * How long a failed fetch is remembered, in seconds (15 minutes) — short,
     * so an outage or a rotated key does not hide the stars for hours.
     */
    private const NEGATIVE_TTL = 900;

    /**
     * Timeout of a request to RatingStar in seconds.
     */
    private const HTTP_TIMEOUT = 5;

    /**
     * Path suffix of the JSON-LD endpoint. `schema=product` tells RatingStar
     * that this plugin can print the Product type; the answer is a Product
     * only when that is set in the RatingStar backend.
     */
    private const JSONLD_SUFFIX = '.jsonld?schema=product';

    /**
     * schema.org types RatingStar answers with.
     */
    private const ACCEPTED_TYPES = ['LocalBusiness', 'Product'];

    /**
     * Flags that keep the JSON safe inside a <script> block: a review text or
     * company name containing "</script>" cannot end the block.
     */
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * API key a seal module registered for the current page ('' = none).
     *
     * @var  string
     */
    private string $pageKey = '';

    /**
     * Base origin that belongs to the page key ('' = site-wide origin).
     *
     * @var  string
     */
    private string $pageOrigin = '';

    /**
     * Saved items already checked in this request. com_modules fires
     * onExtensionAfterSave twice for one save.
     *
     * @var  array<string, bool>
     */
    private array $checked = [];

    /**
     * Whether the log file of this plugin is registered in this request.
     *
     * @var  boolean
     */
    private bool $loggerAdded = false;

    /**
     * Constructor.
     *
     * @param   array                            $config        The plugin configuration.
     * @param   CacheControllerFactoryInterface  $cacheFactory  The cache controller factory.
     * @param   HttpFactory                      $httpFactory   The HTTP client factory.
     */
    public function __construct(
        array $config,
        private CacheControllerFactoryInterface $cacheFactory,
        private HttpFactory $httpFactory
    ) {
        parent::__construct($config);
    }

    /**
     * Returns the events this subscriber listens to.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead'  => 'onBeforeCompileHead',
            'onExtensionAfterSave' => 'onExtensionAfterSave',
            'onRatingStarPageKey'  => 'onRatingStarPageKey',
        ];
    }

    /**
     * A seal module with its own API key asks for the Google review stars of
     * that profile on the pages it is shown on. The first module wins; the
     * page still gets one block only.
     *
     * @param   Event  $event  Arguments: key, origin.
     *
     * @return  void
     */
    public function onRatingStarPageKey(Event $event): void
    {
        if ($this->pageKey !== '') {
            return;
        }

        $key = self::normalizeKey((string) $event->getArgument('key', ''));

        if ($key === '') {
            return;
        }

        $this->pageKey    = $key;
        $this->pageOrigin = self::normalizeOrigin((string) $event->getArgument('origin', ''));
    }

    /**
     * Adds the server-side JSON-LD to the document head.
     *
     * @param   BeforeCompileHeadEvent  $event  The event.
     *
     * @return  void
     */
    public function onBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $doc = $event->getDocument();

        // Regular front-end HTML pages only (no feeds, no error pages).
        if (!$this->getApplication()->isClient('site') || !$doc instanceof HtmlDocument || $doc->getType() !== 'html') {
            return;
        }

        if (!(int) $this->params->get('jsonld', 1)) {
            return;
        }

        // One block per page: the key of a seal module on this page replaces
        // the site-wide one (branch page → branch, everything else → site).
        $key    = $this->pageKey ?: self::normalizeKey((string) $this->params->get('api_key', ''));
        $origin = ($this->pageKey !== '' ? $this->pageOrigin : '') ?: $this->siteOrigin();

        if ($key === '') {
            return;
        }

        try {
            $json = $this->getJsonLd($origin . '/seal/k/' . rawurlencode($key) . self::JSONLD_SUFFIX);

            if ($json === '') {
                return;
            }

            // The asset name keeps this to a single block; the data-rs-jsonld
            // marker makes seal.js skip its own client-side injection.
            $doc->getWebAssetManager()->addInlineScript(
                $json,
                ['name' => 'plg_system_ratingstar.jsonld'],
                ['type' => 'application/ld+json', 'data-rs-jsonld' => '1']
            );
        } catch (\Throwable $e) {
            // Never break the host page.
        }
    }

    /**
     * After a seal module or this plugin was saved: confirm the profile, the
     * group and the API key against RatingStar, or warn. The values are saved
     * either way, so a typo stays fixable. Saving the plugin also clears the
     * cached JSON-LD, so a new key or origin applies at once.
     *
     * @param   AfterSaveEvent  $event  The save event.
     *
     * @return  void
     */
    public function onExtensionAfterSave(AfterSaveEvent $event): void
    {
        $context = $event->getContext();
        $table   = $event->getItem();

        $isModule = $context === 'com_modules.module' && ($table->module ?? '') === 'mod_ratingstar';
        $isPlugin = $context === 'com_plugins.plugin'
            && ($table->element ?? '') === 'ratingstar' && ($table->folder ?? '') === 'system';

        if (!$isModule && !$isPlugin) {
            return;
        }

        $item = $context . ':' . (int) ($isModule ? ($table->id ?? 0) : ($table->extension_id ?? 0));

        if (isset($this->checked[$item])) {
            return;
        }

        $this->checked[$item] = true;

        try {
            $params = $table->params ?? [];

            if (\is_string($params)) {
                $params = json_decode($params, true);
            }

            $params = \is_array($params) ? $params : [];

            $this->loadLanguage();

            if ($isPlugin) {
                $this->getCache()->clean(self::CACHE_GROUP);
            }

            // A module without its own origin uses the site-wide one.
            $origin = self::normalizeOrigin((string) ($params['base_origin'] ?? ''))
                ?: ($isPlugin ? self::DEFAULT_ORIGIN : $this->siteOrigin());

            $group = trim((string) ($params['group'] ?? ''));
            $slug  = trim((string) ($params['slug'] ?? ''));

            // The group wins over the branch, so that is what gets checked.
            if ($group !== '') {
                $this->verifyProfile($origin . '/g/' . rawurlencode($group), $group, 'GROUP');
            } elseif ($slug !== '') {
                $this->verifyProfile($origin . '/t/' . rawurlencode($slug), $slug, 'SLUG');
            }

            $key = self::normalizeKey((string) ($params['api_key'] ?? ''));

            if ($key !== '') {
                $this->verifyKey($origin . '/seal/k/' . rawurlencode($key) . self::JSONLD_SUFFIX);
            }
        } catch (\Throwable $e) {
            // A failed check must never get in the way of saving.
        }
    }

    /**
     * GETs a public profile page and reports the result as a message.
     *
     * @param   string  $url     The profile URL (/t/<slug> or /g/<group>).
     * @param   string  $handle  The configured slug, for the message.
     * @param   string  $kind    SLUG or GROUP (language key part).
     *
     * @return  void
     */
    private function verifyProfile(string $url, string $handle, string $kind): void
    {
        $app    = $this->getApplication();
        $body   = $this->httpGet($url);
        $handle = htmlspecialchars($handle, ENT_QUOTES, 'UTF-8');

        if ($body === '') {
            $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_RATINGSTAR_' . $kind . '_UNVERIFIED', $handle), 'warning');

            return;
        }

        $name = $this->extractTitle($body);

        if ($name !== '') {
            $app->enqueueMessage(
                Text::sprintf('PLG_SYSTEM_RATINGSTAR_' . $kind . '_OK', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')),
                'message'
            );
        } else {
            $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_RATINGSTAR_' . $kind . '_REACHED', $handle), 'message');
        }
    }

    /**
     * Fetches the JSON-LD of a key (uncached) and reports whether RatingStar
     * knows the key.
     *
     * @param   string  $url  The .jsonld endpoint URL.
     *
     * @return  void
     */
    private function verifyKey(string $url): void
    {
        $app  = $this->getApplication();
        $data = json_decode($this->httpGet($url), true);

        if (!\is_array($data) || !\in_array($data['@type'] ?? null, self::ACCEPTED_TYPES, true)) {
            $app->enqueueMessage(Text::_('PLG_SYSTEM_RATINGSTAR_KEY_UNVERIFIED'), 'warning');

            return;
        }

        $name = \is_string($data['name'] ?? null) ? $data['name'] : '';

        $app->enqueueMessage(
            Text::sprintf('PLG_SYSTEM_RATINGSTAR_KEY_OK', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')),
            'message'
        );
    }

    /**
     * Extracts the profile name from the <title> of a profile page, dropping
     * everything after the first separator (" – ", " · ", " | " …).
     *
     * @param   string  $html  The page HTML.
     *
     * @return  string  The profile name, or '' when no title is found.
     */
    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) !== 1) {
            return '';
        }

        $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = preg_split('/\s+[\x{2013}\x{2014}\-|·]\s+/u', $title);

        return trim(strip_tags($parts[0] ?? $title));
    }

    /**
     * Returns the JSON-LD for a .jsonld endpoint URL, ready to print, or ''
     * when there is nothing valid to print. Successful results are cached for
     * the configured lifetime, failures for a short time only.
     *
     * The cache can fail (folder not writable, Redis or Memcached not
     * reachable). Then nothing is printed and RatingStar is not asked: a
     * result that cannot be stored would be fetched again on every page view.
     * That is why the entry is stored as "failed" before the request — only a
     * cache that takes this very entry gets a request.
     *
     * @param   string  $url  The .jsonld endpoint URL.
     *
     * @return  string
     */
    private function getJsonLd(string $url): string
    {
        $id = md5($url);

        try {
            $cache = $this->getCache();
            $entry = $cache->get($id, self::CACHE_GROUP);

            if (\is_array($entry) && isset($entry['time'], $entry['json']) && \is_string($entry['json'])) {
                // A stored failure only counts while it is fresh.
                if ($entry['json'] !== '' || time() - (int) $entry['time'] < self::NEGATIVE_TTL) {
                    return $entry['json'];
                }
            }

            // Reserve the entry as "failed" before asking RatingStar.
            if ($cache->store(['time' => time(), 'json' => ''], $id, self::CACHE_GROUP) !== true) {
                $this->logCacheFailure('The entry could not be stored.');

                return '';
            }
        } catch (\Throwable $e) {
            $this->logCacheFailure($e->getMessage());

            return '';
        }

        $json = $this->buildJsonLd($this->httpGet($url));

        if ($json === '') {
            // The reserved entry already says "failed".
            return '';
        }

        try {
            if ($cache->store(['time' => time(), 'json' => $json], $id, self::CACHE_GROUP) !== true) {
                $this->logCacheFailure('The fetched JSON-LD could not be stored.');
            }
        } catch (\Throwable $e) {
            $this->logCacheFailure($e->getMessage());
        }

        return $json;
    }

    /**
     * Writes a cache failure to the log file of this plugin. Logging must not
     * break the page either — the log folder may be read-only as well.
     *
     * @param   string  $reason  What went wrong, as a sentence.
     *
     * @return  void
     */
    private function logCacheFailure(string $reason): void
    {
        try {
            if (!$this->loggerAdded) {
                Log::addLogger(['text_file' => self::LOG_FILE], Log::ALL, [self::LOG_CATEGORY]);
                $this->loggerAdded = true;
            }

            Log::add(
                'The cache "' . self::CACHE_GROUP . '" is not usable: ' . $reason . ' Without a working cache the '
                . 'JSON-LD (Google stars) is not printed and RatingStar is not asked. Check that the cache folder is '
                . 'writable and that the cache handler of the global configuration is available.',
                Log::ERROR,
                self::LOG_CATEGORY
            );
        } catch (\Throwable $e) {
            // Nothing left to do.
        }
    }

    /**
     * Turns a response body into the JSON that is printed: decoded, checked
     * to be a LocalBusiness or Product object and encoded again — the remote text itself
     * never reaches the page. The content is passed on unchanged otherwise
     * (a bare LocalBusiness stays bare, ratings are never invented).
     *
     * @param   string  $body  The response body.
     *
     * @return  string  The JSON, or '' when the body is not usable.
     */
    private function buildJsonLd(string $body): string
    {
        if ($body === '') {
            return '';
        }

        $data = json_decode($body, true);

        if (!\is_array($data) || !\in_array($data['@type'] ?? null, self::ACCEPTED_TYPES, true)) {
            return '';
        }

        $json = json_encode($data, self::JSON_FLAGS);

        return \is_string($json) ? $json : '';
    }

    /**
     * The cache controller for the JSON-LD. Caching is forced on, so it also
     * works while Joomla's global cache is switched off (the default).
     *
     * @return  OutputController
     */
    private function getCache(): OutputController
    {
        $minutes = (int) $this->params->get('cache_lifetime', self::CACHE_MINUTES);

        return $this->cacheFactory->createCacheController('output', [
            'defaultgroup' => self::CACHE_GROUP,
            'caching'      => true,
            'lifetime'     => $minutes >= 1 ? $minutes : self::CACHE_MINUTES,
        ]);
    }

    /**
     * Performs a GET request. Every error is swallowed.
     *
     * @param   string  $url  The URL to fetch.
     *
     * @return  string  The response body on HTTP 200, otherwise ''.
     */
    private function httpGet(string $url): string
    {
        try {
            $response = $this->httpFactory->getHttp([])->get($url, [], self::HTTP_TIMEOUT);

            if ($response->getStatusCode() === 200) {
                return (string) $response->getBody();
            }
        } catch (\Throwable $e) {
            // Treated like any other failed request.
        }

        return '';
    }

    /**
     * The site-wide base origin from the plugin settings.
     *
     * @return  string
     */
    private function siteOrigin(): string
    {
        return self::normalizeOrigin((string) $this->params->get('base_origin', '')) ?: self::DEFAULT_ORIGIN;
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
     * Returns '' for anything that is not an http(s) URL.
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
