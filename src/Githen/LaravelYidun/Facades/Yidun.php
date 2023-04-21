<?php 
namespace Githen\LaravelYidun\Facades;

use Illuminate\Support\Facades\Facade;

class Yidun extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'yidun';
    }

}