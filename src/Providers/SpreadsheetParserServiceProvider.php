<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use ImageSpark\SpreadsheetParser\Factory;


class SpreadsheetParserServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Factory::class, function($app) {
            $parser = new Factory($app);

            return $parser;
        });
    }

    public function provides()
    {
        return [
            Factory::class,
        ];
    }
}