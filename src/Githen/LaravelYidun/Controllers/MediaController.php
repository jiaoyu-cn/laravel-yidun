<?php

namespace Githen\LaravelYidun\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MediaController extends Controller
{

    public function __construct()
    {
    }

    public function callback(Request $request)
    {
        $callbackData = $request->input('callbackData', '');
        if (empty($callbackData)) {
            return response()->json(['code' => "500", "msg" => "参数错误"]);
        }
        $callbackData = json_decode(trim($callbackData), true);
        $callbackTarget = config('yidun.media_solution.callback_target');
        if (!empty($callbackTarget)) {
            app($callbackTarget)->handle($callbackData);
        }
        return response()->json(['code' => "200", "msg" => "接收成功"]);
    }

}
