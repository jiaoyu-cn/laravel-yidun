# laravel-yidun

基于laravel的网易易盾内容安全

[![image](https://img.shields.io/github/stars/jiaoyu-cn/laravel-yidun)](https://github.com/jiaoyu-cn/laravel-yidun/stargazers)
[![image](https://img.shields.io/github/forks/jiaoyu-cn/laravel-yidun)](https://github.com/jiaoyu-cn/laravel-yidun/network/members)
[![image](https://img.shields.io/github/issues/jiaoyu-cn/laravel-yidun)](https://github.com/jiaoyu-cn/laravel-yidun/issues)

## 安装

```shell
composer require githen/laravel-yidun:~v1.0.0

# 迁移配置文件
php artisan vendor:publish --provider="Githen\LaravelYidun\YidunServiceProvider"
```

## 配置文件说明

在config/logging.php中添加yidun日志配置项

```php
'yidun' => [
    'driver' => 'daily',
    'path' => storage_path('logs/yidun/yidun.log'),
    'level' => 'debug',
    'days' => 7,
    'permission' => 0770,
],
```        

生成`yidun.php`上传配置文件

```php
<?php
return [
    /*
    |--------------------------------------------------------------------------
    | 网易易盾配置
    |--------------------------------------------------------------------------
    |
    */
    // 产品秘钥
    'secret_id' => '',
    'secret_key' => '',
    'log_channel' => 'yidun',//写入日志频道，空不写入
    // 融媒体解决方案
    'media_solution' => [
        'callback_url' => config('app.url').'/yidun/media/callback',// 融媒体回调地址
        'callback_target' => \App\Extend\Yidun\Media::class,// 融媒体回调处理类 未设置或者空，不触发
    ]
];
```
