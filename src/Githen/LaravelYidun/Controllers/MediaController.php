<?php

namespace Githen\LaravelYidun\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

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
            $taskId = $request->input('task_id', '');
            if (!empty($taskId)) {
                $resp = app('yidun')->mediaCallbackQuery($taskId);
                $callbackData = $resp['data']['0'] ?? [];
            }
        } else {
            $secretId = $request->input('secretId', '');
            $signature = $request->input('signature', '');
            $checkSignature = app('yidun')->genSignature(['secretId' => $secretId, 'callbackData' => $callbackData]);
            if ($signature != $checkSignature) {
                return response()->json(['code' => "500", "msg" => "校验失败"]);
            }
            $callbackData = json_decode(trim($callbackData), true);
        }
        if (empty($callbackData)) {
            return response()->json(['code' => "500", "msg" => "参数错误"]);
        }
        $this->showMessage($callbackData);
        // 处理通用结构
        $data = [];
        $antispam = $callbackData['antispam'] ?? [];
        if (empty($antispam)) {
            return response()->json(['code' => "500", "msg" => "参数错误"]);
        }
        $data = [
            'task_id' => $antispam['taskId'],
            'data_id' => $antispam['dataId'] ?? '',
            'check_status' => $antispam['checkStatus'],
            'suggestion' => $antispam['suggestion'],
            'failure_reason' => [],
            'evidences' => [],
        ];
        if ($antispam['checkStatus'] == 3) {
            $code = array_column($antispam['solutionEnrichEvidence']['failedUnits'] ?? [], 'failureReason');
            $data['failure_reason'] = app('yidun')->mediaFailureReasonByCode($code);
        }
        $evidences = $antispam['evidences'] ?? [];
        foreach ($evidences as $oneType => $oneEvidences) {
            foreach ($oneEvidences as $oneEvidence) {
                $field = $oneEvidence['field'] ?? '';
                if (empty($field)) {
                    continue;
                }
                if (!isset($data['evidences'][$field])) {
                    $data['evidences'][$field] = [];
                }
                //
                if (in_array($oneType, ['texts', 'images'])) {
                    $commonData = [
                        'type' => ($oneType == 'texts' ? 'text' : 'image'),
                        'data_id' => $oneEvidence['dataId'] ?? '',
                        'suggestion' => $oneEvidence['suggestion'],
                    ];
                    if ($commonData['type'] == 'image') {
                        $commonData['status'] = $oneEvidence['status'] ?? 2;
                        $commonData['failure_reason'] = '';
                        $commonData['url'] = '';
                        if ($commonData['status'] == 3) {
                            $commonData['failure_reason'] = implode(',',
                                app('yidun')->mediaFailureReasonByCode($oneEvidence['failureReason'] ?? 0));
                        }
                    }
                    $labels = $this->covertLabels($commonData['type'], $oneEvidence['labels'] ?? []);
                    $data['evidences'][$field][] = array_merge($commonData, ['labels' => $labels]);
                }
                if ($oneType == 'audios') {
                    $commonData = [
                        'type' => 'audio',
                        'data_id' => $oneEvidence['dataId'] ?? '',
                        'suggestion' => $oneEvidence['suggestion'],
                        'status' => $oneEvidence['status'] ?? 2,
                        'duration' => $oneEvidence['duration'] ?? 0,
                        'failure_reason' => '',
                    ];
                    if ($commonData['status'] == 3) {
                        $commonData['failure_reason'] = implode(',',
                            app('yidun')->mediaFailureReasonByCode($oneEvidence['failureReason'] ?? 0));
                    }
                    foreach ($oneEvidence['segments'] ?? [] as $segment) {
                        $labels = $this->covertLabels('audio', $segment['labels'] ?? [],
                            ['start_time' => bcdiv($segment['startTime'] ?? 0, 1000, 2), 'end_time' => bcdiv($segment['endTime'] ?? 0, 1000, 2)]);
                        $data['evidences'][$field][] = array_merge($commonData, ['labels' => $labels]);
                    }
                }
                if ($oneType == 'audiovideos') {
                    $commonData = [
                        'data_id' => $oneEvidence['dataId'] ?? '',
                        'suggestion' => $oneEvidence['suggestion'],
                        'status' => $oneEvidence['status'] ?? 2,
                        'duration' => $oneEvidence['duration'] ?? 0,
                        'failure_reason' => '',
                    ];
                    if ($commonData['status'] == 3) {
                        $commonData['failure_reason'] = implode(',',
                            app('yidun')->mediaFailureReasonByCode($oneEvidence['failureReason'] ?? 0));
                    }
                    foreach ($oneEvidence['evidences'] ?? [] as $secondType => $secondEvidence) {
                        $commonData['type'] = $secondType;
                        foreach ($secondEvidence[($secondType == 'audio' ? 'segments' : 'pictures')] ?? [] as $segment) {
                            $labels = $this->covertLabels($commonData['type'], $segment['labels'] ?? [],
                                ['start_time' => bcdiv($segment['startTime'] ?? 0, 1000, 2), 'end_time' => bcdiv($segment['endTime'] ?? 0, 1000, 2)]);
                            $data['evidences'][$field][] = array_merge($commonData, ['labels' => $labels]);
                        }
                    }
                }
                if ($oneType == 'files') {
                    foreach ($oneEvidence['evidences'] ?? [] as $secondType => $secondEvidences) {
                        $commonData = [
                            'type' => $secondType == 'texts' ? 'text' : 'image',
                            'data_id' => $oneEvidence['dataId'] ?? '',
                            'suggestion' => $oneEvidence['suggestion'],
                        ];
                        if ($commonData['type'] == 'image') {
                            $commonData['status'] = $oneEvidence['status'] ?? 2;
                            $commonData['failure_reason'] = '';
                            if ($commonData['status'] == 3) {
                                $commonData['failure_reason'] = implode(',',
                                    app('yidun')->mediaFailureReasonByCode($oneEvidence['failureReason'] ?? 0));
                            }
                        }
                        foreach ($secondEvidences as $secondEvidence) {
                            if ($commonData['type'] == 'image') {
                                $commonData['url'] = $secondEvidence['imageUrl'] ?? '';
                            }
                            $labels = $this->covertLabels($commonData['type'], $secondEvidence['labels'] ?? []);
                            $data['evidences'][$field][] = array_merge($commonData, ['labels' => $labels]);
                        }
                    }
                }
            }
        }
        $callbackTarget = config('yidun.media_solution.callback_target');
        if (!empty($callbackTarget)) {
            app($callbackTarget)->handle($data);
        }
        return response()->json(['code' => "200", "msg" => "接收成功"]);
    }


    private function showMessage($data, $status = 1)
    {
        $channel = config('yidun.log_channel', '');
        if (empty($channel)) {
            return;
        }
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        if ($status == 1) {
            Log::channel($channel)->info($data);
            return;
        }
        Log::channel($channel)->error($data);
    }

    private function covertLabels($type, $labels, $appendParams = [])
    {
        $data = [];
        foreach ($labels as $label) {
            if (empty($label['subLabels'])) {
                continue;
            }
            foreach ($label['subLabels'] as $subLabel) {

                foreach ($subLabel['details']['hitInfos'] ?? [] as $hitInfo) {
                    $item = [
                        "label" => $label['label'],
                        "sub_label" => $subLabel['subLabel'],
                        "value" => $hitInfo['value'] ?? ''
                    ];
                    if ($type == 'text') {
                        $positions = [];
                        foreach ($hitInfo['positions'] ?? [] as $position) {
                            $positions[] = [
                                'start_pos' => $position['startPos'] ?? 0,
                                'end_pos' => $position['endPos'] ?? 0,
                            ];
                        }
                        $item['positions'] = $positions;
                    } else if ($type == 'image') {
                        $item['x1'] = $hitInfo['x1'] ?? 0;
                        $item['y1'] = $hitInfo['y1'] ?? 0;
                        $item['x2'] = $hitInfo['x2'] ?? 0;
                        $item['y2'] = $hitInfo['y2'] ?? 0;
                    } else if ($type == 'audio') {
                        $item['start_time'] = $appendParams['start_time'] ?? 0;
                        $item['end_time'] = $appendParams['end_time'] ?? 0;
                    } else if ($type == 'video') {
                        $item['x1'] = $hitInfo['x1'] ?? 0;
                        $item['y1'] = $hitInfo['y1'] ?? 0;
                        $item['x2'] = $hitInfo['x2'] ?? 0;
                        $item['y2'] = $hitInfo['y2'] ?? 0;
                        $item['start_time'] = $appendParams['start_time'] ?? 0;
                        $item['end_time'] = $appendParams['end_time'] ?? 0;
                    }
                    $data[] = $item;
                }
                // 没有命中词
                if (empty($subLabel['details']['hitInfos'])) {
                    if (in_array($type, ['audio', 'video'])) {
                        $item = [
                            "label" => $label['label'],
                            "sub_label" => $subLabel['subLabel'],
                            "value" => '',
                            'start_time' => $appendParams['start_time'] ?? 0,
                            'end_time' => $appendParams['end_time'] ?? 0,
                        ];
                        $data[] = $item;
                    }
                }
            }
        }
        return $data;
    }
}
