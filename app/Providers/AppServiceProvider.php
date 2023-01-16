<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $socialite = $this->app->make(Factory::class);

        $socialite->extend('ec-cube', function () use ($socialite) {
            $config = config('services.ec-cube');

            return $socialite->buildProvider(EccubeProvider::class, $config);
        });

        $socialite->extend('base', function () use ($socialite) {
            $config = config('services.base');

            return $socialite->buildProvider(BaseProvider::class, $config);
        });
    }
}
