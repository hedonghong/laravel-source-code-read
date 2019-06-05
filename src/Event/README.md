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
            #Dispatcher是事件真正的注册和实现触发的类，【events注册的就是Dispatcher类对象】
            #QueueResolver就是貌似于数组的一样类
            return (new Dispatcher($app))->setQueueResolver(function () use ($app) {
                return $app->make(QueueFactoryContract::class);
            });
        });
    }
}
#看过上一节的都知道在延迟注册服务提供者的地方提供了一个when的函数，其中就是包含着事件，那里也可以注册事件哦
foreach ($manifest['when'] as $provider => $events) {
    #注册服务提供者的监听事件(后面会介绍laravel的事件机制)
    $this->registerLoadEvents($provider, $events);
}

protected function registerLoadEvents($provider, array $events)
{
    if (count($events) < 1) {
        return;
    }

    $this->app->make('events')->listen($events, function () use ($provider) {
        $this->app->register($provider);
    });
}
#好了，laravel的事件机制，初步到这里！
```

```php

#接下来，我再讲下laravel框架如何加载用户定义或者自带的事件
#由开篇让你提早阅读的手册知道事件类和监听者类放在app/events|listeners文件夹中，并且在
#app/Providers/EventServiceProvider.php的$listen数组中注册，我们可以注意到app/Providers/目录下都是系统自带的服务提供者，并且这几个
#类名称都出现在config/app.php的providers数组中了，那就是说明框架在启动的时候就会首先加载这些服务，并初始化
#下面看看EventServiceProvider类

namespace App\Providers;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
#注意EventServiceProvider类
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     * 注意用户新定义事件在这里
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];
    /**
     * Register any events for your application.
     * 初始化
     * @return void
     */
    public function boot()
    {
        #其实是父类EventServiceProvider的注册$listen数组中的事件
        parent::boot();
        //
    }
}

#父类EventServiceProvider的boot()方法
public function boot()
{
    foreach ($this->listens() as $event => $listeners) {
        foreach ($listeners as $listener) {
            //其实用了event的门面调用listen方法注册了事件
            Event::listen($event, $listener);
        }
    }

    foreach ($this->subscribe as $subscriber) {
        Event::subscribe($subscriber);
    }
}

```

## 2、event的触发

> 上面讲的是把事件-监听者建立关系，并且把这个份关系保存起来，那什么时候用到这份关系呢，那就是下面的事件的触发

```php
#栗子来源于中文手册
namespace App\Http\Controllers;

use App\Order;
use App\Events\OrderShipped;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    /**
     * 将传递过来的订单发货。
     *
     * @param  int  $orderId
     * @return Response
     */
    public function ship($orderId)
    {
        $order = Order::findOrFail($orderId);

        // 订单发货逻辑…
        #event()函数
        #vendor/laravel/framework/src/Illuminate/Foundation/helpers.php中可以找到
        event(new OrderShipped($order));
    }
}

```

```php

if (! function_exists('event')) {
    function event(...$args)
    {
        #直接调用容器中的events实例调用dispatch方法进行唤醒监听者
        #忘记的events在容器中存的是哪里对象的同学可以到上面看下中文大括号
        return app('events')->dispatch(...$args);
    }
}

```
> 到这里可以先到src/Event/Src/Dispatcher.php看下源码注册，着重注意事件注册方法listen()和触发方法dispatch();若想看框架的源码可以到vendor/laravel/framework/src/Illuminate/Events




