#23种设计模式之一门面模式(外观模式)
> 虽然很想在这里说下23种设计模式，但是本次主题是了解laraval如何应用门面模式的，它的应用给我们带来什么的方便？

借用lavavel学院的一句话就是：引入门面角色之后，用户只需要直接与门面角色交互，用户与子系统之间的复杂关系由门面角色来实现，从而降低了系统的耦合度

[学院君门面介绍](https://laravelacademy.org/post/9536.html)

下面内容基于你知道composer自动加载和laravel容器的概念

## 1、几种常见的laravel框架门面

|门面|类|服务容器绑定|
| ------ | ------ | ------ |
|App|Illuminate\Foundation\Application|app|
|Artisan|Illuminate\Contracts\Console\Kernel|artisan|
|DB|Illuminate\Database\DatabaseManager|db|
|Route|Illuminate\Routing\Router|router|
|View|Illuminate\View\Factory|view|
|Validator|Illuminate\Validation\Factory|validator|

```php
#路由的编写方式一
App::make('router')->get('/', function () {
  return view('welcome');
});
#路由的编写方式二
Route::get('/', function () {
  return view('welcome');
});
```

## 2、Facade的实现

源码所在：
```php
vendor/laravel/framework/src/Illuminate/Support/Facades 很多自带的门面都在这里
vendor/laravel/framework/src/Illuminate/Support/Facades/Route.php Route门面
```

### 2.1、 了解如何实现之前，我们看看Route门面是怎么实现的
> 根据vendor/laravel/framework/src/Illuminate/Support/Facades/Route.php类文件可以知道，作者通过继承Facade并且实现静态方法getFacadeAccessor即可

```php
class Route extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'router';
    }
}
```
> 那这样子的话，所有的实现秘密都在Facade类中了，其实基类有如下一个核心函数，具体源码解析请看Src中
> 当运行Route::get()时，发现门面Route没有静态get()函数，PHP就会调用这个魔术函数__callStatic

```php
    public static function __callStatic($method, $args)
    {
        #获得对象实例
        $instance = static::getFacadeRoot();
        if (! $instance) {
            throw new RuntimeException('A facade root has not been set.');
        }
        #对象实例调用对应的函数
        return $instance->$method(...$args);
    }
```
> 如果看过Facade.PHP的人都知道该类中有一个变量static::$app(容器)，该变量是怎么注入？

```php
    #在laravel启动的时候
    $app = new Illuminate\Foundation\Application(
        $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
    );
    #会调用到下面的类
    #vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/RegisterFacades.php
    
    #vendor/laravel/framework/src/Illuminate/Foundation/Application.php
    #这个函数是会把vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap中类运行一下
    #其中就涉及到RegisterFacades.php
    public function bootstrapWith(array $bootstrappers)
    {
        $this->hasBeenBootstrapped = true;

        foreach ($bootstrappers as $bootstrapper) {
            $this['events']->dispatch('bootstrapping: '.$bootstrapper, [$this]);

            $this->make($bootstrapper)->bootstrap($this);

            $this['events']->dispatch('bootstrapped: '.$bootstrapper, [$this]);
        }
    }
    
```
## 3、等等，为什么Route可以调用Illuminate\Support\Facades\Route？
> 其实就是别名了[class_alias](https://www.php.net/manual/zh/function.class-alias.php)
```php
#实现跟下面差不多
class_alias('Illuminate\Support\Facades\Route', 'Route');
```
> 在laravel框架中我们可以看到[config/app.php](https://github.com/laravel/laravel/blob/master/config/app.php)

```php
...
'aliases' => [
        'App' => Illuminate\Support\Facades\App::class,
        'Arr' => Illuminate\Support\Arr::class,
        'Artisan' => Illuminate\Support\Facades\Artisan::class,
...
        'Route' => Illuminate\Support\Facades\Route::class,
...
```
> laravel框架在哪里进行别名设置呢？

注意下框架中的这个文件[app/Http/Kernel.php](https://github.com/laravel/laravel/blob/master/app/Http/Kernel.php)，而我这个代码中中没有，因为我只拉取了laravel底层框架

```php
#模拟框架启动~~~

#public/index.php

#bootstrap/app.php

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

#重点：绑定了Illuminate\Contracts\Http\Kernel::class处理类的实现类为App\Http\Kernel::class
#App\Http\Kernel::class是来自于app/Http/Kernel.php(这个教程中没有)

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);
...
return $app;

#再回到pulic/index.php

$app = require_once __DIR__.'/../bootstrap/app.php';
#通过容器获取Illuminate\Contracts\Http\Kernel::class，按照之前约定真正获取的是App\Http\Kernel::class的实现对象
#App\Http\Kernel::class是来自于app/Http/Kernel.php(这个教程中没有)
#我们可以发现app/Http/Kernel.php继承于Illuminate\Foundation\Http\Kernel
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    #laravel通过全局$_SERVER数组构造一个Http请求的语句，接下来会调用Http的内核函数handle，本次重点不在这里，大家知道一下就好
    $request = Illuminate\Http\Request::capture()
);
$response->send();
$kernel->terminate($request, $response);

#好吧，上面其他代码可以先忽略，我们先看看App\Http\Kernel类的handle方法,请结合源码注释src/Facade/Src/Kernel.php，里面引导你看下去

    public function handle($request)
    {
        try {
            #在handle函数方法中enableHttpMethodParameterOverride函数是允许在表单中使用delete、put等类型的请求
            $request->enableHttpMethodParameterOverride();

            $response = $this->sendRequestThroughRouter($request);
        } catch (Exception $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        } catch (Throwable $e) {
            $this->reportException($e = new FatalThrowableError($e));

            $response = $this->renderException($request, $e);
        }

        $this->app['events']->dispatch(
            new Events\RequestHandled($request, $response)
        );

        return $response;
    }

```

> 再等等，我们Route门面中getFacadeAccessor方法返回的不是router吗？

```php
#模拟框架启动~~~
#public/index.php
#bootstrap/app.php
$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);
#在vendor/laravel/framework/src/Illuminate/Foundation/Application.php构造方法__construct中最后运行的方法registerCoreContainerAliases

    public function registerCoreContainerAliases()
    {
        foreach ([
             ...
            'router' => [\Illuminate\Routing\Router::class, \Illuminate\Contracts\Routing\Registrar::class, \Illuminate\Contracts\Routing\BindingRegistrar::class],
             ...
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }
#后面通过门面调用路由功能代码的时候就会使用到Facade的resolveFacadeInstance
    protected static function resolveFacadeInstance($name)
    {
        if (is_object($name)) {
            return $name;
        }

        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }
        #static::$app['router']获取到真是的路由类对象
        return static::$resolvedInstance[$name] = static::$app[$name];
    }
```

## 4、完了，最后想实现自己门面的，可以去看下学院君的自定义门面教程
[自定义门面](https://laravelacademy.org/post/817.html)