#COMPOSER自动加载

## 1、前言
在了解laravel框架之前，composer的自动加载需要了解下的，涉及到知识点
> PHP自动加载功能 

> PHP命名空间

> PSR0/PSR4标准

## 2、PHP自动加载功能 

### 2.1、php中如何引入一个文件？

```php
include / require;
include_once / require_once

//include 在引入不存文件时产生一个警告且脚本还会继续执行
//require 会导致一个致命性错误且脚本停止执行
```

我们想想如果一个功能的完成需要使用到很多外部类的时候，我们是不是要写很多require/include，就像C语言中的#include，那么这部分既然是统一而且每个项目都会遇到的问题，那就给出统一的解决方案吧！

### 2.2、PHP5自动加载
>PHP程序有使用类时才自动加载文件，而不是一开始就将所有的类文件include进来。不用开发者再一一include了

#### 2.2.1、自动加载函数 __autoload()
>在项目运行前实现下面方法，方法内部实现为“组合类的真实磁盘所在路径”，并且使用include / require 加载进来，这样PHP程序在遇到未加载的类时候，就会调用 [__autoload()](https://www.php.net/manual/zh/function.autoload.php) 方法进行类加载
```php
#该方法需要一个类名作为传入参数
function __autoload($classname) {
    $filePath = 'xxx/xx/xx/'.$classname.'.php';
    require_once ($filePath);
}
```
> 但是__autoload()函数有个很明显的缺点就是我们开发人员需要知道所有的“组合类的真实磁盘所在路径”，并且在该方法中实现，好吧，如果我们项目调用不用开发人员的造的“轮子”呢？

#### 2.2.2、SPL Autoload
> 为了解决上面__autoload的问题，我们需要一个类似数据库的功能存储这些外部文件对应路径的映射关系，在我们调用类的时候，自动加载一层层去查找(我们可以想象这些关系存在一个数组里面从0开始查~一直到末端)，那么我们引入SPL的Autoload

>[SPL](https://www.php.net/manual/zh/book.spl.php)

>[SPL Autoload](https://www.php.net/manual/zh/ref.spl.php) 有下面几个方法，不清楚的同学可以去手册查查

```php
spl_autoload_register：注册__autoload()函数
spl_autoload_unregister：注销已注册的函数
spl_autoload_functions：返回所有已注册的函数
spl_autoload_call：尝试所有已注册的函数来加载类
spl_autoload ：__autoload()的默认实现
spl_autoload_extionsions： 注册并返回spl_autoload函数使用的默认文件扩展名。
```
> 一般情况下我们使用spl_autoload_register代替__autoload，spl_autoload_register可以注册一个匿名函数、函数或者一个类，并且可以多次注册，把不同的实现查找外部文件所在路径的类和方法都注册到“一个数组”中，在遇到未加载的类就从这里一个一个实现类和方法中查找并加载！

> 在有了上面的“加载工具”之后，我们又想到一个问题就是不同人，文件存储路径和命名规范不一样，实在不好管理！那么PHP专门对这块进行了一个规范整理，那就是[PSR0](https://www.php-fig.org/psr/psr-0/)和[PSR4](https://www.php-fig.org/psr/psr-4/)

## 3、PSR0 / PSR4

> 在了解PSR标准前，我们需要知道两个事:

### 3.1、命名空间 Namespace
>命名空间用来解决在编写类库代码中名称一样的问题，还有一个前提是我们在同一个路径下不能有两个相同名称文件

PHP 命名空间可以解决以下两类问题：
用户编写的代码与PHP内部的类/函数/常量或第三方类/函数/常量之间的名字冲突。
为很长的标识符名称(通常是为了缓解第一类问题而定义的)创建一个别名（或简短）的名称，提高源代码的可读性。

>总的来说命名空间提供一种将相关的类、函数和常量组合到一起的途径(一般情况下命名空间以文件夹路径一致，用于区别不同文件夹下相同名称文件)

### 3.2、PSR标准包括什么？
> PSR0、PSR4是用于规范文件与命名空间的映射关系的，就是说用于规范文件存储路径和命名空间的关系，以方便用于后面统一加载

```php
//PSR标准包含有如下：
PSR-0 (Autoloading Standard) 自动加载标准
PSR-1 (Basic Coding Standard)基础编码标准
PSR-2 (Coding Style Guide) 编码风格向导
PSR-3 (Logger Interface) 日志接口
PSR-4 (Improved Autoloading) 自动加载的增强版，替换PSR-0
```
### 3.3、PSR0
>下面针对PSR0中Mandatory进行中文翻译
```php
1、一个完全合格的namespace和class必须符合这样的结构：\<Vendor Name>\(<Namespace>\)*<Class Name>
    举个例子：有命名空间 Monolog\Handler\Curl;
    Monolog就是Vendor Name，也就是第三方库的名字，Handler是Namespace名字，一般是我们命名空间的一些属性信息；最后Curl就是我们命名空间的名字
2、每个namespace必须有一个顶层的namespace（"Vendor Name"扩展名字）
    每个命名空间都要有一个类似于Monolog的顶级命名空间，为什么要有这种规则呢？因为PSR0标准只负责顶级命名空间之后的映射关系，也就是Handler\Curl这一部分，关于Monolog应该关联到哪个目录，那就是用户或者框架自己定义的了。所谓的顶层的namespace，就是自定义了映射关系的命名空间（在这里可以看看composer.json里面定义），一般就是提供者名字（第三方库的名字）。
    顶级命名空间是自动加载的基础。如果有个命名空间是Monolog\Handler\Curl，还有个命名空间是Monolog\Handler\xx1\Curl,如果没有顶级命名空间，我们就得写两个路径和这两个命名空间相对应，如果再有Curl1、Curl2呢。有了顶层命名空间Monolog，那我们就仅仅需要一个目录对应即可，剩下的就利用PSR标准去解析就行了。
3、每个namespace可以有多个子namespace
    命名空间下面可以有很多子命名空间，放多少层命名空间都是自己定义
4、当从文件系统中加载时，每个namespace的分隔符(/)要转换成 DIRECTORY_SEPARATOR(操作系统路径分隔符)
5、在类名中，每个下划线(_) 符号要转换成 DIRECTORY_SEPARATOR(操作系统路径分隔符)。在namespace中，下划线 _ 符号是没有（特殊）意义的。
    4、5条都是把分隔符或者下划线变成对应系统的目录分隔符，再说一次顶级命名空间会有一个真正路径目录，其余子命名空间与目录对应
    如Monolog\Handler\Curl 对应目录可能是/src/Monolog/Handler/Curl.php
6、当从文件系统中载入时，合格的namespace和class一定是以 .php 结尾的
7、verdor name, namespaces, class名可以由大小写字母组合而成（大小写敏感的）
```
>上面说的其中命名空间的命名规范是1、2、3、7，文件后缀规范是6，文件所在目录规范市4、5，一般情况下一个文件定义一个命名空间，完整的命名空间最后是类目，前面或许是文件夹名称

>一个类文件可以被自动加载需要满足4、5，新建的文件和命名空间需要满足1、2、3、6、7

### 3.4、PSR4

>与PSR0的区别在于

```php
在类名中使用下划线没有任何特殊含义。
命名空间与文件目录的映射方法有所调整。
类名必须要和对应的文件名要一模一样，大小写也要一模一样。

假如有一个命名空间：Forexample/name，Forexample是顶级命名空间，其存在着用户定义的与目录的映射关系："Forexample\" => "src/"
按照PSR0标准，映射后的文件目录是:src/Forexample/name.php
按照PSR4标准，映射后的文件目录就会是:src/name.php
原因就是怕命名空间太长导致目录层次太深，使得命名空间和文件目录的映射关系更加灵活。
```

```php
#PSR-0风格文件夹

-vendor/
| -vendor_name/
| | -package_name/
| | | -src/
| | | | -Vendor_Name/
| | | | | -Package_Name/
| | | | | | -ClassName.php       #ClassName.php的命名空间为 Vendor_Name\Package_Name\ClassName
| | | -tests/
| | | | -Vendor_Name/
| | | | | -Package_Name/
| | | | | | -ClassNameTest.php   #ClassNameTest.php的命名空间为 Vendor_Name\Package_Name\ClassNameTest

{
    "autoload": {
        "psr-0": { "Vendor_Name\\": ["src/", "tests/"] }
    }
}

#PSR-4风格文件夹

-vendor/
| -vendor_name/
| | -package_name/
| | | -src/
| | | | -ClassName.php       #ClassName.php的命名空间为 Vendor_Name\Package_Name\ClassName
| | | -tests/
| | | | -ClassNameTest.php   #ClassNameTest.php的命名空间为 Vendor_Name\Package_Name\ClassNameTest

{
    "autoload": {
        "psr-4": { "Vendor_Name\\": ["src/", "tests/"] }
    }
}

```


