<?php
namespace Laravel\Tests;

use Laravel\Container\People;
use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;

use Laravel\Container\Girl;
use Laravel\Container\AnimalInterface;
use Laravel\Container\Dog;
use Laravel\Container\Cat;
use Laravel\Container\People1;
use Laravel\Container\People2;
use Laravel\Container\People3;


class IocTest extends TestCase
{
    /**
     * 绑定自身
     */
    public function testIoc1()
    {
        $app = new Container();
        $app->bind('Laravel\Dog', null);
        $app->bind('People', 'Laravel\People');
        $people = $app->make('People');
        echo $people->putDog();
    }

    /**
     * 绑定闭包
     */
    public function testIoc2()
    {
        $app = new Container();
        $app->bind('Laravel\Dog', function () {
            $obj = new \Laravel\Dog;
            $obj->name = '小黑';
            return $obj;
        });
        $app->bind('Dog', function () {
            $obj = new \Laravel\Dog;
            $obj->name = '小白';
            return $obj;
        });
        $dog = $app->make('Laravel\Dog');
        echo $dog->call();

        $app->bind('People', 'Laravel\People');
        $people = $app->make('People');
        $people->putDog();

    }

    /**
     * 接口绑定
     */
    public function testIoc3()
    {
        $app = new Container();
        $app->bind('Laravel\AnimalInterface', 'Laravel\Dog');
        $app->bind('People', 'Laravel\People2');
        $people = $app->make('People');
        $people->putDog();
    }

    /**
     * bindif
     */
    public function testIoc4()
    {
        $app = new Container();
        $app->bind('name', function(){
            return 'hedongdong';
        });

        $app->bindif('name', function(){
            return 'huanglizhong';
        });

        $app->make('name');

        //$name =  $app->factory('name');
        //echo $name();
    }

    /**
     * singleton
     * instance
     */
    public function testIoc5()
    {
        $app = new Container();
        $app->singleton('Dog', '\Laravel\Dog');

        var_dump($app->make('Dog') === $app->make('Dog'));

        $app->bind('Dog1', '\Laravel\Dog');

        var_dump($app->make('Dog1') === $app->make('Dog1'));

        $dog2 = new \Laravel\Dog;
        $app->instance('Dog2', $dog2);

        var_dump($app->make('Dog') === $app->make('Dog'));
    }

    /**
     * contextual绑定
     */
    public function testIoc6()
    {
        $app = new Container();
        $app->when(Girl::class)
            ->needs(AnimalInterface::class)
            ->give(function () {
                $dog = new Dog;
                $dog->name = '小白';
                return $dog;
            });
        $app->bind('Laravel\Girl');
        $girl = $app->make('Laravel\Girl');
        $girl->putDog();
    }

    /**
     * tag绑定 tagged解析
     */
    public function testIoc7()
    {
        $app = new Container();
        $app->bind('Dog', 'Laravel\Dog');
        $app->bind('Cat', 'Laravel\Cat');
        $app->tag(['Dog', 'Cat'], 'Pets');
        var_dump($app->tagged('Pets'));
    }

    /**
     * extend扩展绑定
     */
    public function testIoc8()
    {
        $app = new Container();
        $obj = new \StdClass;
        $obj->foo = 'foo';
        $app->instance('foo', $obj);
        var_dump($app->make('foo')->foo);
        $app->extend('foo', function ($obj, $app) {
            $obj->foo = 'foo1';
            return $obj;
        });
        var_dump($app->make('foo')->foo);

        $app->bind('Dog', Dog::class);
        $app->extend('Dog', function ($obj, $app) {
            $obj->name = 'dogname';
            return $obj;
        });
        var_dump($app->make('Dog'));
    }

    /**
     * rebinding扩展绑定(重新绑定)
     */
    public function testIoc9()
    {
        $app = new Container();
        $app->singleton('People', function ($app) {
            $people = new People3($app->make(AnimalInterface::class));

            //注释掉这个看效果
            $app->rebinding(AnimalInterface::class, function ($app, $pet) use ($people) {
                $people->setPet($pet);
            });

            return $people;
        });

        $app->instance(AnimalInterface::class, new Dog);
        $people = $app->make('People');
        $people->putPet();
        $app->instance(AnimalInterface::class, new Cat);
        $people->putPet();
    }

    /**
     * refresh
     */
    public function testIoc10()
    {
        $app = new Container();
        $app->singleton('People', function ($app) {
            $people = new People3($app->make(AnimalInterface::class));
            $app->refresh(AnimalInterface::class, $people, 'setPet');
            return $people;
        });
        $app->instance(AnimalInterface::class, new Dog);
        $people = $app->make('People');
        $people->putPet();
        $app->instance(AnimalInterface::class, new Cat);
        $people->putPet();
    }

    /**
     * bindmethod
     */
    public function testIoc11()
    {
        $app = new Container();
        $app->bind('Dog', Dog::class);
        $app->bind('People', function() use ($app) {return new People1($app->make('Dog'));});

        $app->call(People1::class.'@putDog');

        $app->bindMethod(People1::class.'@putDog', function ($people, $app) {
            $people->name = '2';
            return $people->putDog();
        });
        $app->call(People1::class.'@putDog');
    }

    /**
     * resolving和afterResolving
     */
    public function testIoc12()
    {
        $app = new Container();
        $app->bind('Dog', Dog::class);
        $app->bind('People', function() use ($app) {return new People1($app->make('Dog'));});

        $app->resolving(function ($object, $app) {
            echo 'resolving' . PHP_EOL;
        });

        $app->afterResolving(function ($object, $app) {
            echo 'afterResolving' . PHP_EOL;
        });

        $app->resolving('Dog', function ($object, $app) {
            echo 'Dog - resolving' . PHP_EOL;
        });

        $app->afterResolving(function ($object, $app) {
            echo 'Dog - afterResolving' . PHP_EOL;
        });

        $people = $app->make('People');

    }

    /**
     * wrap 和 factory
     */
    public function testIoc13()
    {
        $app = new Container();
        $app->bind('name', function(){
            return 'hedongdong';
        });

        $app->make('name');

        $name =  $app->factory('name');
        echo $name().PHP_EOL;

        $wrap = $app->wrap(function($one, $two) {
            echo $one.PHP_EOL;
            echo $two.PHP_EOL;
        }, [1, 2]);
        $wrap();
    }
}