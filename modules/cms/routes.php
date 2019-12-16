<?php

use Cms\Classes\Sitemap;

/**
 * Register CMS routes before all user routes.
 */
App::before(function ($request) {
    /*
     * Extensibility
     */
    Event::fire('cms.beforeRoute');

    Route::get('sitemap.xml', function () {
        return \Response::make((new Sitemap)->generate())->header('Content-Type', 'application/xml');
    });

    /*
     * The CMS module intercepts all URLs that were not
     * handled by the back-end modules.
     */
    Route::any('{slug}', 'Cms\Classes\CmsController@run')->where('slug', '(.*)?')->middleware('web');

    /*
     * Extensibility
     */
    Event::fire('cms.route');
});
