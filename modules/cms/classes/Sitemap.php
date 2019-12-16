<?php namespace Cms\Classes;

use Carbon\Carbon;
use Cms\Classes\Theme;
use Cms\Classes\Page;

class  Sitemap
{
    /**
     * Maximum URLs allowed (Protocol limit is 50k)
     */
    const MAX_URLS = 50000;
    /**
     * Maximum generated URLs per type
     */
    const MAX_GENERATED = 10000;

    /**
     * @var integer A tally of URLs added to the sitemap
     */
    protected $urlCount = 0;

    private $xml;
    private $urlSet;

    function generate()
    {
        // get all pages of the current theme
        $pages = Page::listInTheme(Theme::getEditTheme());
        foreach ($pages as $page) {
            if (!$page->enabled_in_sitemap) continue;

            $this->addItemToSet(Item::asCmsPage($page));
        }

        return $this->make();
    }

    protected function makeRoot()
    {
        if ($this->xml !== null)
            return $this->xml;

        $xml = new \DOMDocument;
        $xml->encoding = 'UTF-8';

        return $this->xml = $xml;
    }

    protected function makeUrlSet()
    {
        if ($this->urlSet !== null)
            return $this->urlSet;

        $xml = $this->makeRoot();
        $urlSet = $xml->createElement('urlset');
        $urlSet->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urlSet->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $urlSet->setAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');
        $xml->appendChild($urlSet);

        return $this->urlSet = $urlSet;
    }

    protected function addItemToSet(Item $item)
    {
        $xml = $this->makeRoot();
        $urlSet = $this->makeUrlSet();
        $urlElement = $this->makeUrlElement(
            $xml,
            url($item->loc),
            (new Carbon($item->lastmod))->format('c'),
            $item->changefreq,
            $item->priority
        );

        if (!$urlElement)
            return $urlSet;

        $urlSet->appendChild($urlElement);
    }

    protected function makeUrlElement($xml, $pageUrl, $lastModified, $frequency, $priority)
    {
        if ($this->urlCount >= self::MAX_URLS)
            return false;

        $this->urlCount++;
        $url = $xml->createElement('url');
        $pageUrl && $url->appendChild($xml->createElement('loc', $pageUrl));
        $lastModified && $url->appendChild($xml->createElement('lastmod', $lastModified));
        $frequency && $url->appendChild($xml->createElement('changefreq', $frequency));
        $priority && $url->appendChild($xml->createElement('priority', $priority));

        return $url;
    }

    protected function make()
    {
        $this->makeUrlSet();

        return $this->xml->saveXML();
    }
}

class Item
{
    public $loc, $lastmod, $priority, $changefreq;

    function __construct($url = null, $lastmod = null, $priority = null, $changefreq = null)
    {
        $this->loc = $url;
        $this->lastmod = $lastmod;
        $this->priority = $priority;
        $this->changefreq = $changefreq;
    }

    public static function asCmsPage($page)
    {
        return new Self(
            $page->url,
            $page->lastmod ?: Carbon::createFromTimestamp($page->mtime),
            $page->sitemap_priority,
            $page->sitemap_changefreq
        );
    }

}
