# Event-事件的实现

开篇点明一下，laravel的事件在于特定的场景下进行事件埋点触发事件，其实就是SPL中的观察者(订阅模式)的模式，具体用到的知识点有闭包、自动加载函数等。最大的作用实现解耦！
同一个事件可以触发多个监听者类(方法)，具体需要自己多多体会哈，当然在laravel中有的事件还有ORM事件！

## 1、event注册

```php
#在vendor/laravel/framework/src/Illuminate/Foundation/Application.php中有方法registerBaseServiceProviders


protected function registerBaseServiceProviders()
{
    $this->register(new EventServiceProvider($this));
    $this->register(new LogServiceProvider($this));
    $this->register(new RoutingServiceProvider($this));
}
    
#其中vendor/laravel/framework/src/Illuminate/Events/EventServiceProvider.php
class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        #在容器中注册events单例实例
        $this->app->singleton('events', function ($app) {
            #Dispatcher是事件真正的注册和实现触发的类
            #QueueResolver就是貌似于数组的一样类
            return (new Dispatcher($app))->setQueueResolver(function () use ($app) {
                return $app->make(QueueFactoryContract::class);
            });
        });
    }
}
#好了，laravel的事件机制，初步到这里！
```

