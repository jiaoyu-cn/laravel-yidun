<?php

namespace Githen\LaravelYidun\App\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MediaController extends Controller
{

    public function __construct()
    {
    }

    /**
     * 融媒体解决方案推送回调
     * @return void
     */
    public function callback(Request $request)
    {
        $callbackData = $request->input('callbackData', '');
        if (empty($callbackData)) {
            // 支持task_id 更新
            $taskId = $request->input('task_id', '');
            if (!empty($taskId)) {
                $resp = app('jiaoyu.yidun')->mediaCallbackQuery($taskId);
                if ($resp['code'] != '0000') {
                    return response()->json(['code' => "500", "msg" => $resp['message']]);
                }
                $callbackData = $resp['data']['0'] ?? [];
            }
        } else {
            $secretId = $request->input('secretId', '');
            $signature = $request->input('signature', '');
            $checkSignature = app('jiaoyu.yidun')->genSignature(['secretId' => $secretId, 'callbackData' => $callbackData]);
            if ($signature != $checkSignature) {
                return response()->json(['code' => "500", "msg" => "校验失败"]);
            }
            $callbackData = json_decode(trim($callbackData), true);
        }
        app('jiaoyu.yidun')->showMessage($callbackData);
        // 处理通用结构
        $covertData = app('jiaoyu.yidun')->mediaCallbackCovert($callbackData);
        if ($covertData['code'] != '0000') {
            return response()->json(['code' => "500", "msg" => $covertData['message']]);
        }
        $callbackTarget = config('yidun.media_solution.callback_target');
        if (!empty($callbackTarget)) {
            app($callbackTarget)->handle($covertData['data'], empty($taskId) ? 'callback' : 'query');
        }
        return response()->json(['code' => "200", "msg" => "接收成功"]);
    }

}
