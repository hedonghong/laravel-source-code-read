<?php

namespace Illuminate\Foundation\Bootstrap;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Facade;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Contracts\Foundation\Application;

class RegisterFacades
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     * 清除了Facade中的缓存
     * 设置Facade的Ioc容器
     * 获得我们前面讲的config文件夹里面app文件aliases别名映射数组
     * 使用aliases实例化初始化AliasLoader
     * 调用AliasLoader->register()
     */
    public function bootstrap(Application $app)
    {
        Facade::clearResolvedInstances();

        Facade::setFacadeApplication($app);
        #好吧前两句清理下门面类的实例缓存和重新设置容器
        #app.aliases是什么？我这里再贴出来看看
        /*
        ...
        'aliases' => [
        'App' => Illuminate\Support\Facades\App::class,
        'Arr' => Illuminate\Support\Arr::class,
        'Artisan' => Illuminate\Support\Facades\Artisan::class,
        ...
        'Route' => Illuminate\Support\Facades\Route::class,
        ...
        */
        #看到没？'Route' => Illuminate\Support\Facades\Route::class，好了接着register()方法
        AliasLoader::getInstance(array_merge(
            $app->make('config')->get('app.aliases', []),
            $app->make(PackageManifest::class)->aliases()
        ))->register();
        #其实看类目就知道AliasLoader是一个别名注释类
        /*
         #vendor/laravel/framework/src/Illuminate/Foundation/AliasLoader.php
            public function register()
            {
                if (! $this->registered) {
                    $this->prependToLoaderStack();

                    $this->registered = true;
                }
            }

            protected function prependToLoaderStack()
            {
                spl_autoload_register([$this, 'load'], true, true);
            }
        */
        #做的就是把$app->make('config')->get('app.aliases', [])拿到的别名数组进行load方法的class_alias($this->aliases[$alias], $alias);别名设置
        /*
         #vendor/laravel/framework/src/Illuminate/Foundation/AliasLoader.php
            public function load($alias)
            {
                if (static::$facadeNamespace && strpos($alias, static::$facadeNamespace) === 0) {
                    $this->loadFacade($alias);

                    return true;
                }

                if (isset($this->aliases[$alias])) {
                    return class_alias($this->aliases[$alias], $alias);
                }
            }
        */
        #看到这里，可以回到本章的README.md了，因为后面还有一个问题就是为什么我们Route门面的getFacadeAccessor方法返回的不是router吗？
        #门面标识Route与router有什么关系？
    }
}
