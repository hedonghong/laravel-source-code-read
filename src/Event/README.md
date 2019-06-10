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

## 3、ORM事件

>按照惯例，我们先看下学院君的[ORM事件教程](https://laravelacademy.org/post/9713.html)

>Eloquent也支持模型事件——当模型被创建、更新或删除的时候触发相应事件，Eloquent目前支持八种事件类型：creating、created、updating、updated、saving、saved、deleting、deleted。
 
>deleting和deleted很好理解，在删除模型时触发，deleting在删除操作前执行，deleted在删除完成后执行。
 
>当创建模型时，依次执行saving、creating、created和saved，同理在更新模型时依次执行saving、updating、updated和saved。无论是使用批量赋值（create/update）还是直接调用save方法，都会触发对应事件（前提是注册了相应的模型事件）。

### 3.1、通过静态方法实现监听事件定义

>使用于简单地监听个别模型的一两个事件

```php
// app/Providers/EventServiceProvider.php

public function boot()
{
    parent::boot();

    // 监听模型获取事件
    User::retrieved(function ($user) {
        Log::info('从模型中获取用户[' . $user->id . ']:' . $user->name);
    });
}

```

### 3.2、通过观察者监听模型事件定义
>使用于针对特定模型监听多个事件，并统一生成和放置

首先，我们通过 Artisan 命令初始化针对 User 模型的观察者：

```php
php artisan make:observer UserObserver --model=User
```

默认生成的 UserObserver 会为 created、 updated、deleted、restored、forceDeleted（强制删除） 事件定义一个空方法：
```php
<?php

namespace App\Observers;

use App\User;

class UserObserver
{
    /**
     * Handle the user "created" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function created(User $user)
    {
        //
    }

    /**
     * Handle the user "updated" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function updated(User $user)
    {
        //
    }

    /**
     * Handle the user "deleted" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function deleted(User $user)
    {
        //
    }

    /**
     * Handle the user "restored" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function restored(User $user)
    {
        //
    }

    /**
     * Handle the user "force deleted" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function forceDeleted(User $user)
    {
        //
    }
}
```

你可以把前面定义的 retrived、deleting、deleted 事件监听代码迁移过来，也可以将不需监听的事件方法移除，这里我们将编写保存模型时涉及的模型事件，包括 saving、creating、updating、updated、created、saved：

```php
<?php

namespace App\Observers;

use App\User;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function saving(User $user)
    {
        Log::info('即将保存用户到数据库[' . $user->id . ']' . $user->name);
    }

    public function creating(User $user)
    {
        Log::info('即将插入用户到数据库[' . $user->id . ']' . $user->name);
    }

    public function updating(User $user)
    {
        Log::info('即将更新用户到数据库[' . $user->id . ']' . $user->name);
    }

    public function updated(User $user)
    {
        Log::info('已经更新用户到数据库[' . $user->id . ']' . $user->name);
    }

    public function created(User $user)
    {
        Log::info('已经插入用户到数据库[' . $user->id . ']' . $user->name);
    }

    public function saved(User $user)
    {
        Log::info('已经保存用户到数据库[' . $user->id . ']' . $user->name);
    }
}

```

编写好观察者后，需要将其注册到 User 模型上才能生效，我们可以在 EventServiceProvider 的 boot 方法中完成该工作：

```php
public function boot()
{
    parent::boot();

    User::observe(UserObserver::class);

    ...
}
```

### 3.3、通过订阅者监听模型事件定义

>使用于比较复杂的监听事件定义，并统一放置

栗子，分别定义一个删除前事件类和删除后事件类。我们通过 Artisan 命令来完成事件类初始化：

```php
php artisan make:event UserDeleting // app/Events/UserDeleting.php
php artisan make:event UserDeleted // app/Events/UserDeleted.php
```
接下来，我们要在 User 模型类中建立模型事件与自定义事件类的映射，这可以通过 $dispatchesEvents 属性来完成：
```php
protected $dispatchesEvents = [
    'deleting' => UserDeleting::class,
    'deleted' => UserDeleted::class
];


```

这样，当我们触发 deleting 和 deleted 事件时，底层会将其转化为触发 UserDeleting 和 UserDeleted 事件。

最后，我们还要监听上述自定义的事件类，我们可以通过在 EventServiceProvider 的 listen 属性中为每个事件绑定对应的监听器类，通过为某个模型类创建一个事件订阅者类来统一处理该模型中的所有事件。在 app/Listeners 目录下创建一个 UserEventSubscriber.php 文件作为订阅者类，编写代码如下：
```php
<?php

namespace App\Listeners;

use App\Events\UserDeleted;
use App\Events\UserDeleting;
use Illuminate\Support\Facades\Log;

class UserEventSubscriber
{
    /**
     * 处理用户删除前事件
     */
    public function onUserDeleting($event) {
        Log::info('用户即将删除[' . $event->user->id . ']:' . $event->user->name);
    }

    /**
     * 处理用户删除后事件
     */
    public function onUserDeleted($event) {
        Log::info('用户已经删除[' . $event->user->id . ']:' . $event->user->name);
    }

    /**
     * 为订阅者注册监听器
     *
     * @param  Illuminate\Events\Dispatcher  $events
     */
    public function subscribe($events)
    {
        $events->listen(
            UserDeleting::class,
            UserEventSubscriber::class . '@onUserDeleting'
        );

        $events->listen(
            UserDeleted::class,
            UserEventSubscriber::class . '@onUserDeleted'
        );
    }
}

```

最后，我们在 EventServiceProvider 中注册这个订阅者，使其生效：
```php
// app/Providers/EventServiceProvider.php

protected $subscribe = [
    UserEventSubscriber::class
];

```

### 3.4、ORM事件定义的注册

上面说到的3.1和3.2即通过静态方法和观察者注册事件，我们可以看下源码，抽取部分讲解

```php
//vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasEvents.php
#saved事件
public static function saved($callback)
{
    static::registerModelEvent('saved', $callback);
}

protected static function registerModelEvent($event, $callback)
{
    if (isset(static::$dispatcher)) {
        #延迟绑定，即调用类名称
        $name = static::class;
        #比如User类即注册事件eloquent.saved: User
        static::$dispatcher->listen("eloquent.{$event}: {$name}", $callback);
    }
}

#observe
public static function observe($classes)
{
    $instance = new static;

    foreach (Arr::wrap($classes) as $class) {
        $instance->registerObserver($class);
    }
}

/**
 * Register a single observer with the model.
 * #注册observe($classes)中$classes带有的事件处理函数
 * @param  object|string $class
 * @return void
 */
protected function registerObserver($class)
{
    $className = is_string($class) ? $class : get_class($class);

    foreach ($this->getObservableEvents() as $event) {
        if (method_exists($class, $event)) {
            static::registerModelEvent($event, $className.'@'.$event);
        }
    }
}
#系统自带的
public function getObservableEvents()
{
    return array_merge(
        [
            'retrieved', 'creating', 'created', 'updating', 'updated',
            'saving', 'saved', 'restoring', 'restored',
            'deleting', 'deleted', 'forceDeleted',
        ],
        #用户自定义扩展的
        $this->observables
    );
}

```

### 3.5、ORM事件定义的触发

```php

#vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php
#使用save()方法做为栗子说明

public function save(array $options = [])
{
    $query = $this->newModelQuery();
    #简单看下这里判断是否定义了对应的saving事件
    #如果有运行，如果返回false责终止ORM的save操作
    if ($this->fireModelEvent('saving') === false) {
        return false;
    }


    if ($this->exists) {
        $saved = $this->isDirty() ?
                    $this->performUpdate($query) : true;
    }


    else {
        $saved = $this->performInsert($query);

        if (! $this->getConnectionName() &&
            $connection = $query->getConnection()) {
            $this->setConnection($connection->getName());
        }
    }

    if ($saved) {
        $this->finishSave($options);
    }

    return $saved;
}

#vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasEvents.php

protected function fireModelEvent($event, $halt = true)
{
    #是否有事件分发器，没有就不运行事件机制
    if (! isset(static::$dispatcher)) {
        return true;
    }

    // First, we will get the proper method to call on the event dispatcher, and then we
    // will attempt to fire a custom, object based event for the given event. If that
    // returns a result we can return that result, or we'll call the string events.
    $method = $halt ? 'until' : 'dispatch';

    #事件运行后结果处理函数filterModelEventResults
    $result = $this->filterModelEventResults(
    #fireCustomModelEvent真是触发事件监听的代码
        $this->fireCustomModelEvent($event, $method)
    );

    if ($result === false) {
        return false;
    }

    return ! empty($result) ? $result : static::$dispatcher->{$method}(
        "eloquent.{$event}: ".static::class, $this
    );
}

#vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php
public function until($event, $payload = [])
{
    #还记得之前介绍事件的源码吗dispatch的第三个参数
    #触发事件时有一个参数 halt，这个参数如果是 true 的时候，只要有一个监听类返回了结果，
    #那么就会立刻返回
    return $this->dispatch($event, $payload, true);
}

#fireCustomModelEvent真是触发事件监听的代码
protected function fireCustomModelEvent($event, $method)
{
    if (! isset($this->dispatchesEvents[$event])) {
        return;
    }

    $result = static::$dispatcher->$method(new $this->dispatchesEvents[$event]($this));

    if (! is_null($result)) {
        return $result;
    }
}

```




