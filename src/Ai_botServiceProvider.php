<?php 
namespace kolya2320\Ai_bot;

use EvolutionCMS\ServiceProvider;

class Ai_botServiceProvider extends ServiceProvider
{
    protected $namespace = 'ai_bot';
    
    public function register()
    {
        $this->loadPluginsFrom(
            dirname(__DIR__) . '/plugins/'
        );
        
        $this->publishes([
            __DIR__ . '/../publishable/assets' => MODX_BASE_PATH . 'assets',
        ]);
        
        $this->app->registerRoutingModule(
            'BotAI module',
            __DIR__ . '/../routes_module.php'
        );
    }
    
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadViewsFrom(__DIR__ . '/../views', $this->namespace);
    }
}