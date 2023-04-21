# laravel-yidun
基于laravel的网易易盾内容安全

[![image](https://img.shields.io/github/stars/jiaoyu-cn/laravel-yidun)](https://github.com/jiaoyu-cn/laravel-yidun/stargazers)
[![image](https://img.shields.io/github/forks/jiaoyu-cn/laravel-yidun)](https://github.com/jiaoyu-cn/laravel-yidun/network/members)
[![image](https://img.shields.io/github/issues/jiaoyu-cn/laravel-yidun)](https://github.com/jiaoyu-cn/laravel-yidun/issues)

## 安装

```shell
composer require githen/laravel-yidun:^v1.0.0

# 迁移配置文件
php artisan vendor:publish --provider="Githen\LaravelYidun\YidunServiceProvider"
```