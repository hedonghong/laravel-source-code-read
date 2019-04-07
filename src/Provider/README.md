#服务提供者
>理解服务提供者，需要有控制反转（IoC）和请求生命周期的基础、框架中自带的服务提供者有：

```php
#app/Providers/
AppServiceProvider.php
AuthServiceProvider.php
BroadcastServiceProvider.php
EventServiceProvider.php
RouteServiceProvider.php

```

## 1、laravel中自定义服务提供者(抄录自：学院君的[服务提供者](https://laravelacademy.org/post/19435.html))

>服务提供者主要分为两部分：注册（register）、引导或初始化（boot）。注册负责进行向容器注册脚本，但要注意祖册部分不要有对未知事物的依赖，如果有就要移步到引导部分中。


```php
#1、继承自Illuminate\Support\ServiceProvider

#2、实现父类的register 和 boot，其中register只负责注册到服务容器中，boot负责初始化或引导

#3、register
    #一般实现为：
    #非延迟加载的方式
    namespace App\Providers;
    use Riak\Connection;
    use Illuminate\Support\ServiceProvider;
    
    class RiakServiceProvider extends ServiceProvider{
        /**
         * 在容器中注册绑定.
         *
         * @return void
         */
        public function register()
        {
            $this->app->singleton(Connection::class, function ($app) {
                return new Connection(config('riak'));
            });
        }
    }
    #延迟加载的方式
    namespace App\Providers;
    
    use Riak\Connection;
    use Illuminate\Support\ServiceProvider;
    use Illuminate\Contracts\Support\DeferrableProvider;
    
    class RiakServiceProvider extends ServiceProvider implements DeferrableProvider
    {
        /**
         * Register the service provider.
         *
         * @return void
         */
        public function register()
        {
            $this->app->singleton(Connection::class, function ($app) {
                #通过配置读取更加灵活
                return new Connection($app['config']['riak']);
            });
        }
    
        /**
         * Get the services provided by the provider.
         *
         * @return array
         */
        public function provides()
        {
            return [Connection::class];
        }
    }
    #从代码上看前后者区别在于现实了多一个接口DeferrableProvider和provides()方法

#4、boot
#会在所有的服务提供者都注册完成之后才会执行，所以当你想在服务绑定完成之后，通过容器解析出其它服务，做一些初始化工作的时候，
#那么就可以这些逻辑写在boot方法里面。因为boot方法执行的时候，所有服务提供者都已经被注册完毕了，所以在boot方法里面能够确保其它服务都能被解析出来。

    public function boot()
    {
        view()->composer('view', function () {
            //
        });
    }
#5、注册服务提供者，在配置文件 config/app.php中
#所有服务提供者都是通过配置文件config/app.php 中进行注册，该文件包含了一个列出所有服务提供者名字的providers数组，默认情况下，其中列出了所有核心服务提供者，这些服务提供者启动 Laravel核心组件，比如邮件、队列、缓存等等。
#要注册你自己的服务提供者，只需要将其追加到该数组中即可：

'providers' => [
    // 其它服务提供者
    App\Providers\ComposerServiceProvider::class,
],

```

> 自定义服务提供者就介绍这么多，那怎么用呢？我们简单做个栗子吧！

```php
#需求是我们需要一个输入打印类代替php原生的输出echo

#第一步
namespace App\Http\Print;
class MyPrint
{
    /**
     * @param $str
     */
    public  function  printMy($str){
        print_r($str);
    }
 
}

#第二步
php artisan make:provider MyPrint

#第三步生成MyPrintProvider类，稍作修改

class MyPrintProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
 
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(LogLogic::class, function ($app) {
            return new MyPrint();
        });
    }
}

#第四步config/app.php中加入服务提供者

App\Providers\MyPrintProvider::class,

#第五步在controller中使用
namespace App\Http\Controllers;
 
use App\Http\Print\MyPrint;
use Illuminate\Http\Request;
 
class UsersController extends Controller
{
    public function index(Request $requser,MyPrint $myPrint){
       $myPrint->printMy('hello');
    }
}　


```

>对于上面的栗子，会有这样子的疑问，类都定义了，可以到处用了呀，因为laravel也会通过反射功能实现自动注入，为什么要通过服务提供者的方式使用呢？

```
1、但是我们纵观整个laravel以容器作为基础进行依赖注入的，同样我们的服务也尽量遵守这种约定，通过register方法中进行实例化，以及绑定到容器
2、如果我们的服务提供类继承自接口而实现的类，我们可以进行扩展的时候替换原来的类就显得很简单，不用修改每一个用到的地方只需要修改register中绑定的实现类即可

```

## 2、回到正题laravel是怎么实现这些服务提供者的功能？

> 服务提供者其实就是提供一个途径给用户注册一个类到容器中，达到解耦的目的！

> 看过之前容器启动过程的我们都知道服务提供者的启动由类 \Illuminate\Foundation\Bootstrap\RegisterProviders::class负责。

> 然后在通过\Illuminate\Foundation\Bootstrap\BootProviders::class执行boot方法

> 这里提前告知该类用于加载所有服务提供者的register函数，并保存延迟加载的服务的信息，以便实现延迟加载。

```php
#我们看下\Illuminate\Foundation\Bootstrap\RegisterProviders::class先吧
#代码在框架中的路径为vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/RegisterProviders.php
class RegisterProviders
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $app->registerConfiguredProviders();
    }
}
#回到的容器类vendor/laravel/framework/src/Illuminate/Foundation/Application.php中的registerConfiguredProviders
    public function registerConfiguredProviders()
    {
        #获取配置文件中的服务提供数组，我们现在应该明白为什么我们要把服务提供者加入config/app.php了吧
        #Collection::make($this->config['app.providers'])创建一个服务者集合
        #$providers该集合通过Illuminate\区别是否系统自带服务和自定义服务
        #觉得有疑惑的可以调试下tests/CollectionTest.php的testMake()方法
        $providers = Collection::make($this->config['app.providers'])
                        ->partition(function ($provider) {
                            #并过滤出系统providers
                            return Str::startsWith($provider, 'Illuminate\\');
                        });
        #之前在registerBaseBindings方法中绑定在PackageManifest类中的providers数组拼接，通过load方法加载它们
        #我们回忆下vendor/laravel/framework/src/Illuminate/Foundation/Application.php容器类的构造方法：
        /*
            public function __construct($basePath = null)
            {
                if ($basePath) {
                    $this->setBasePath($basePath);
                }
                #重点关注这个方法
                $this->registerBaseBindings();
        
                $this->registerBaseServiceProviders();
        
                $this->registerCoreContainerAliases();
            }
            
            protected function registerBaseBindings()
            {
                static::setInstance($this);
        
                $this->instance('app', $this);
        
                $this->instance(Container::class, $this);
                #向容器绑定PackageManifest::class类，我们找下PackageManifest类的providers()
                /*
                    public function providers()
                    {
                        #获取配置文件中的服务提供者数组
                        return collect($this->getManifest())->flatMap(function ($configuration) {
                            return (array) ($configuration['providers'] ?? []);
                        })->filter()->all();
                    }
                */
                $this->instance(PackageManifest::class, new PackageManifest(
                    new Filesystem, $this->basePath(), $this->getCachedPackagesPath()
                ));
            }
        */
        #splice方法就是拼接方法
        $providers->splice(1, 0, [$this->make(PackageManifest::class)->providers()]);
        #在ProviderRepository对象中，传入服务容器、文件系统操作对象、与之前缓存的服务提供者路径
        (new ProviderRepository($this, new Filesystem, $this->getCachedServicesPath()))
                    ->load($providers->collapse()->toArray());
    }

#ProviderRepository类如何加载服务，请看源码注释src/Provider/Src/ProviderRepository.php
#从ProviderRepository类构造方法和load方法看起

```