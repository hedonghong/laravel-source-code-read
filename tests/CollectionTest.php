<?php
namespace Laravel\Tests;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Illuminate\Auth\AuthServiceProvider;



class CollectionTest extends TestCase
{
    /**
     * 绑定自身
     */
    public function testMake()
    {
        $providersConfig = [
            MyPrint::class,//随便写的，没有这个类
            AuthServiceProvider::class,
        ];
        $providers = Collection::make($providersConfig)
            ->partition(function ($provider) {
                return Str::startsWith($provider, 'Illuminate\\');
            });
    }
}