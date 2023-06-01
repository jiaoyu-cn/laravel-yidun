<?php

namespace Githen\LaravelYidun\Providers;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * 自动注册为服务
 */
class YidunServiceProvider extends LaravelServiceProvider
{
    /**
     * 启动服务
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/config.php' => config_path('yidun.php')]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('yidun', function ($app) {
            return new Client([
                'secret_id' => $app['config']->get('yidun.secret_id'),
                'secret_key' => $app['config']->get('yidun.secret_key'),
            ]);
        });

        // 请求路由
        Route::middleware('web')->post('yidun/media/callback', '\Githen\LaravelYidun\App\Controllers\MediaController@callback')
            ->name('yidun.media.callback.post'); // 融媒体解决方案回调
        Route::middleware('web', 'auth')->get('yidun/media/callback', '\Githen\LaravelYidun\App\Controllers\MediaController@callback')
            ->name('yidun.media.callback.get'); // 融媒体解决方案回调
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('yidun');
    }

}
