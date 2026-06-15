<?php

/**
 * @package     RatingStar.Plugin
 * @subpackage  System.ratingstar
 *
 * @copyright   (C) Baumgärtner Marketing GmbH
 * @license     proprietary
 */

namespace Joomla\Plugin\System\Ratingstar\Extension;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;

\defined('_JEXEC') or die;

/**
 * Server-side Google-Sterne / Rich-Snippet for RatingStar.
 *
 * Fetches the ready-made LocalBusiness JSON-LD from the key-based endpoint
 * (`<origin>/seal/k/<key>.jsonld`), caches it, and injects exactly one
 * <script type="application/ld+json"> into the page head (CONTRACT.md §6–8).
 * The JSON-LD is passed through verbatim — the app is the single source of
 * truth for the schema (bare LocalBusiness on count 0 / lower tiers, optional
 * aggregateRating + review[] above). Never breaks the host page on errors.
 *
 * @since  0.2.0
 */
final class Ratingstar extends CMSPlugin implements SubscriberInterface
{
    /**
     * Default RatingStar origin. The endpoint URL is derived from this one
     * setting (CONTRACT.md §2) — nothing is hardcoded.
     */
    public const DEFAULT_ORIGIN = 'https://ratingstar.de';

    /**
     * Guards against emitting more than one JSON-LD block per request
     * (CONTRACT.md §7).
     *
     * @var  bool
     */
    private static bool $injected = false;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead'  => 'onBeforeCompileHead',
            'onExtensionAfterSave' => 'onExtensionAfterSave',
        ];
    }

    /**
     * Injects the server-side LocalBusiness JSON-LD into the document head.
     *
     * @return  void
     */
    public function onBeforeCompileHead(): void
    {
        if (self::$injected) {
            return;
        }

        $app = $this->getApplication();

        // Frontend HTML documents only.
        if (!$app || !$app->isClient('site')) {
            return;
        }

        $doc = $app->getDocument();

        if (!$doc instanceof HtmlDocument) {
            return;
        }

        $key = $this->normalizeKey((string) $this->params->get('api_key', ''));

        // No / invalid key → emit nothing, never break the host page.
        if ($key === '') {
            return;
        }

        $origin = $this->normalizeOrigin((string) $this->params->get('base_origin', self::DEFAULT_ORIGIN));
        $url    = $origin . '/seal/k/' . rawurlencode($key) . '.jsonld';

        $raw = $this->fetchCached($url);

        if ($raw === '') {
            return;
        }

        // Validate it is the expected LocalBusiness object before emitting —
        // pass it through verbatim (CONTRACT.md §8), never fabricate ratings.
        $data = json_decode($raw, true);

        if (!\is_array($data) || ($data['@type'] ?? null) !== 'LocalBusiness') {
            return;
        }

        self::$injected = true;

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // The data-rs-jsonld marker also makes seal.js suppress its own
        // client-side JSON-LD injection (it bails when it finds
        // script[data-rs-jsonld]) → guarantees exactly one block per page.
        $doc->addCustomTag('<script type="application/ld+json" data-rs-jsonld="1">' . $json . '</script>');
    }

    /**
     * Non-blocking slug verification (CONTRACT.md §3). When a RatingStar seal
     * module is saved, GET <origin>/t/<slug> and confirm the profile via its
     * <title>, or warn — without ever changing the saved value.
     *
     * @param   AfterSaveEvent  $event  The save event.
     *
     * @return  void
     */
    public function onExtensionAfterSave(AfterSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_modules.module') {
            return;
        }

        $table = $event->getItem();

        if (!\is_object($table) || ($table->module ?? '') !== 'mod_ratingstar') {
            return;
        }

        $params = $table->params ?? '';

        if (\is_string($params)) {
            $params = json_decode($params, true) ?: [];
        }

        $params = (array) $params;
        $slug   = trim((string) ($params['slug'] ?? ''));

        if ($slug === '') {
            return;
        }

        $origin = $this->normalizeOrigin((string) ($params['base_origin'] ?? self::DEFAULT_ORIGIN));
        $url    = $origin . '/t/' . rawurlencode($slug);

        $app  = $this->getApplication();
        $body = self::httpFetch($url, 5);

        // Non-blocking: the value is already saved (a typo stays fixable). We
        // only confirm the profile or warn — never reject the save.
        if ($body === '') {
            $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_RATINGSTAR_SLUG_UNVERIFIED', $slug), 'warning');

            return;
        }

        $name = $this->extractTitle($body);

        if ($name !== '') {
            $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_RATINGSTAR_SLUG_OK', $name), 'message');
        } else {
            $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_RATINGSTAR_SLUG_REACHED', $slug), 'message');
        }
    }

    /**
     * Extracts the profile name from a /t/<slug> page's <title> (CONTRACT.md §3),
     * dropping a trailing " – Site name" suffix.
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

        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');

        // Drop a trailing site-name suffix after a dash / pipe separator.
        $parts = preg_split('/\s+[\x{2013}\x{2014}\-|·]\s+/u', $title);

        return trim($parts[0] ?? $title);
    }

    /**
     * Returns the API key only if it matches the canonical `rs_live_<40>` form
     * (CONTRACT.md §1), otherwise an empty string.
     *
     * @param   string  $key  The configured key.
     *
     * @return  string
     */
    private function normalizeKey(string $key): string
    {
        $key = trim($key);

        return preg_match('/^rs_live_[A-Za-z0-9]{40}$/', $key) === 1 ? $key : '';
    }

    /**
     * Normalizes the base origin: trims whitespace and a trailing slash, falls
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

    /**
     * Fetches the JSON-LD, cached via Joomla's callback cache so config / key
     * rotation is picked up after the lifetime (and on a manual cache clear —
     * CONTRACT.md §11).
     *
     * @param   string  $url  The .jsonld endpoint URL.
     *
     * @return  string  The raw JSON, or '' on failure.
     */
    private function fetchCached(string $url): string
    {
        $lifetime = (int) $this->params->get('cache_lifetime', 60);

        if ($lifetime < 1) {
            $lifetime = 60;
        }

        try {
            $cache = Factory::getContainer()
                ->get(CacheControllerFactoryInterface::class)
                ->createCacheController('callback', [
                    'defaultgroup' => 'plg_system_ratingstar',
                    'lifetime'     => $lifetime,
                ]);

            return (string) $cache->get([self::class, 'httpFetch'], [$url, 5], md5($url));
        } catch (\Throwable $e) {
            // Cache unavailable → direct, uncached fetch.
            return self::httpFetch($url, 5);
        }
    }

    /**
     * Performs the HTTP GET. Static so Joomla's callback cache can invoke it.
     * Swallows every error — the host page must never break (CONTRACT.md §9).
     *
     * @param   string  $url      The URL to fetch.
     * @param   int     $timeout  Timeout in seconds.
     *
     * @return  string  The response body on HTTP 200, otherwise ''.
     */
    public static function httpFetch(string $url, int $timeout): string
    {
        try {
            $response = (new HttpFactory())->getHttp([])->get($url, [], $timeout);

            if ($response->getStatusCode() === 200) {
                return (string) $response->getBody();
            }
        } catch (\Throwable $e) {
            // swallow
        }

        return '';
    }
}
