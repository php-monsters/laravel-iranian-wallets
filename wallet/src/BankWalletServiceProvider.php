<?php

namespace App\Packages\wallet\wallet\src;

use App\Packages\wallet\wallet\src\Contracts\Factory;
use Illuminate\Support\ServiceProvider;

/**
 *
 */
class BankWalletServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Factory::class, function ($app) {
            return new WalletManager($app);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPublishing();
    }

    /**
     * @return void
     */
    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/wallet.php' => config_path('wallet.php')
        ], 'config');
    }
}
