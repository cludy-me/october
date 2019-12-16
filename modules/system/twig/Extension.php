<?php

namespace System\Twig;

use Url;
use Twig\Extension\AbstractExtension as TwigExtension;
use Twig\TwigFilter as TwigSimpleFilter;
use Twig\TwigFunction as TwigSimpleFunction;
use System\Classes\MediaLibrary;
use System\Classes\MarkupManager;

/**
 * The System Twig extension class implements common Twig functions and filters.
 *
 * @package october\system
 * @author Alexey Bobkov, Samuel Georges
 */
class Extension extends TwigExtension
{
    /**
     * @var \System\Classes\MarkupManager A reference to the markup manager instance.
     */
    protected $markupManager;

    /**
     * Creates the extension instance.
     */
    public function __construct()
    {
        $this->markupManager = MarkupManager::instance();
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        /*
         * Include extensions provided by plugins
         */
        return $this->markupManager->makeTwigFunctions([
            new TwigSimpleFunction('config', [$this, 'configFunction'], ['is_safe' => ['html']]),
            new TwigSimpleFunction('env', [$this, 'envFunction'], ['is_safe' => ['html']]),
            new TwigSimpleFunction('session', [$this, 'sessionFunction'], ['is_safe' => ['html']]),
            new TwigSimpleFunction('trans', [$this, 'transFunction'], ['is_safe' => ['html']]),
            new TwigSimpleFunction('var_dump', [$this, 'varDumpFunction'], ['is_safe' => ['html']]),

        ]);
    }

    /**
     * Works like the config() helper function.
     *
     * @return mixed
     */
    public function configFunction($key, $default = null)
    {
        return config($key, $default);
    }

    /**
     * Works like the env() helper function.
     *
     * @return mixed
     */
    public function envFunction($key, $default = null)
    {
        return env($key, $default);
    }

    /**
     * Works like the session() helper function.
     *
     * @return mixed
     */
    public function sessionFunction($key)
    {
        return session($key);
    }

    /**
     * Works like the trans() helper function.
     *
     * @return mixed
     */
    public function transFunction($key, $parameters = [])
    {
        return trans($key, $parameters);
    }

    /**
     * Dumps information about a variable.
     *
     * @return mixed
     */
    public function varDumpFunction($expression)
    {
        ob_start();
        var_dump($expression);
        $result = ob_get_clean();
        return $result;
    }

    /**
     * Returns a list of filters this extensions provides.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        /*
         * Include extensions provided by plugins
         */
        return $this->markupManager->makeTwigFilters([
            new TwigSimpleFilter('app', [$this, 'appFilter'], ['is_safe' => ['html']]),
            new TwigSimpleFilter('media', [$this, 'mediaFilter'], ['is_safe' => ['html']]),
            new TwigSimpleFilter('var_dump', [$this, 'varDumpFilter'], ['is_safe' => ['html']]),
        ]);
    }

    /**
     * Returns a list of token parsers this extensions provides.
     *
     * @return array An array of token parsers
     */
    public function getTokenParsers()
    {
        /*
         * Include extensions provided by plugins
         */
        return $this->markupManager->makeTwigTokenParsers([]);
    }

    /**
     * Converts supplied URL to one relative to the website root.
     * @param mixed $url Specifies the application-relative URL
     * @return string
     */
    public function appFilter($url)
    {
        return Url::to($url);
    }

    /**
     * Converts supplied file to a URL relative to the media library.
     * @param string $file Specifies the media-relative file
     * @return string
     */
    public function mediaFilter($file)
    {
        return MediaLibrary::url($file);
    }

    /**
     * Dumps information about a variable.
     *
     * @return mixed
     */
    public function varDumpFilter($expression)
    {
        ob_start();
        var_dump($expression);
        $result = ob_get_clean();
        return $result;
    }

}
