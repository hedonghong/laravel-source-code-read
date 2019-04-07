#环境变量和配置文件加载
> 为什么要把环境变量和配置放在一起呢？首先项目部署环境简单可以分为测试和生产环境，那么配置也应该不一样，在laravel中使用ENV文件区别，那么环境不一样，配置内容也不一样的，基于这样的想法，我们看看这两部分在larave是如何实现的吧！

## 1、环境变量

### 1.1、项目中如何命名.env文件
```php
.env.development
.env.staging 
.env.production
.env.testing
.env.pre-production
...
```
### 1.2、设置系统环境变量
```php
APP_ENV=development
APP_ENV=staging
APP_ENV=production
...
```
### 1.3、APP_ENV 可在 etc/nginx/fastcgi.conf 中设置就行，这样 nginx 会把这些常量传给 PHP 作为环境变量
```php
fastcgi_param  APP_ENV production;
```

### 1.4、请到源码注释src/Config/Src/LoadEnvironmentVariables.php中阅读bootstrap方法
> 如果为什么源码就是这个文件呢？门面中有介绍到src/Facade/Src/Kernel.php的类变量$bootstrappers在Application启动的到时候就会加载
```php
    #环境变量加载
    \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    #配置加载
    \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    #自带和自定义异常处理加载
    \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
    #自带和自定义门面加载
    \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
    #自带和自定义服务提供者加载
    \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    #执行服务提供者们的boot方法
    \Illuminate\Foundation\Bootstrap\BootProviders::class,
```
> vendor/laravel/framework/src/Illuminate/Foundation/Application.php也提供两个方法给用户设置env文件路径和名称

```php
$app = new Illuminate\Foundation\Application(
    realpath(__DIR__.'/../')
);
$app->useEnvironmentPath('xx/xx/xx')
$app->loadEnvironmentFrom('xx.xx')
```

## 2、配置文件加载
> 有了上面环境变量加载的过程了解，我们很清楚配置文件由\Illuminate\Foundation\Bootstrap\LoadConfiguration::class完成

详细可以直接看源码注释吧src/Config/Src/LoadConfiguration.php
代码中主要做如下：
>加载缓存
>若缓存不存在，则利用函数loadConfigurationFiles加载配置文件
>加载环境变量、时间区、编码方式
```php
    public function bootstrap(Application $app)
    {
        $items = [];
        if (file_exists($cached = $app->getCachedConfigPath())) {
            $items = require $cached;

            $loadedFromCache = true;
        }
        $app->instance('config', $config = new Repository($items));

        if (! isset($loadedFromCache)) {
            $this->loadConfigurationFiles($app, $config);
        }
        $app->detectEnvironment(function () use ($config) {
            return $config->get('app.env', 'production');
        });
        date_default_timezone_set($config->get('app.timezone', 'UTC'));
        mb_internal_encoding('UTF-8');
    }
```
>最后怎么获取配置？可以通过config全局函数获取，获取通过学习学院君的配置读取教程吧