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
        
        $this->publishes([
            __DIR__ . '/../publishable/assets' => MODX_BASE_PATH . 'assets',
            __DIR__ . '/../config/ai_bot.php' => MODX_BASE_PATH . 'assets/plugins/BotAI/ai_bot.php',
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
        $configPath = MODX_BASE_PATH . 'assets/plugins/BotAI/ai_bot.php';
        
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'services.yandex_cloud');
        } else {
            $this->mergeConfigFrom(
                __DIR__ . '/../config/ai_bot.php', 'services.yandex_cloud'
            );
        }
    }
    
    protected function mapWebRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
    }
}