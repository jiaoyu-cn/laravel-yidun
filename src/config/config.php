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
        'callback_url' => config('app.url').'/yidun/media/callback',// 融媒体回调地址 未设置或者空，不触发回调
        'callback_target' => \App\Extend\Yidun\Media::class,// 融媒体回调处理类 未设置或者空，不触发
    ]
];
