<?php 
namespace kolya2320\Ai_bot;

use EvolutionCMS\ServiceProvider;
use kolya2320\Ai_bot\Console\Commands\RunAI;

class Ai_botServiceProvider extends ServiceProvider
{
    protected $namespace = 'ai_bot';
    public function register()
    {
        $this->commands([
            RunAI::class,
        ]);
        $this->loadPluginsFrom(
            dirname(__DIR__) . '/plugins/'
        );
        $this->mergeConfigFrom(
            dirname(__DIR__) .'/config/ai_bot.php', 'services.yandex_cloud'
        );
        $this->publishes([
            __DIR__ . '/../publishable/assets'  => MODX_BASE_PATH . 'assets',
        ]);
        $this->app->registerRoutingModule(
            'BotAI module',
            __DIR__ . '/../routes_module.php'
        );
    }
    public function boot()
    {
        $this->mapWebRoutes();
        $this->loadViewsFrom(__DIR__ . '/../views', $this->namespace);
    }
    protected function mapWebRoutes(): void
    {
        $this->loadRoutesFrom( __DIR__ .'/../routes.php'); // Добавляем ваши маршруты
    }
}