<?php

namespace Githen\LaravelYidun\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

trait MediaTrait
{
    /**
     * 融媒体解决方案接口提交
     * @param array $params
     * @return array
     */
    public function mediaSubmit($params)
    {
        $checkData = $this->mediaCheck($params);
        if ($checkData['code'] != '0000') {
            return $this->message('2000', $checkData['mesage']);
        }

        $uri = 'https://as.dun.163.com/v2/mediasolution/submit';
        $params['version'] = 'v2';
        $callbackURL = config('yidun.media_solution.callback_url');
        if (!empty($callbackURL)) {
            $params['callbackUrl'] = $callbackURL;
        }
        $resp = $this->httpPost($uri, ['form_params' => $params]);
        if ($resp['code'] != 200) {
            return $this->message('2000', '检测失败', $resp['data'] ?? '');
        }
        if (empty($resp['result']['antispam'])) {
            return $this->message('2000', '检测结果格式异常');
        }

        return $this->message('0000', '检测成功', $resp['result']['antispam']);
    }

    /**
     * 参数校验
     * @param array $params
     * @return array
     */
    public function mediaCheck($params)
    {
        // 参数校验
        if (!empty($params['title'])) {
            if (mb_strlen($params['title']) > 512) {
                return $this->message('2000', '标题不符合规范，超出512字符');
            }
        }
        if (!empty($params['content'])) {
            $content = [];
            foreach ($params['content'] as $item) {
                if (!isset($content[$item['type']])) {
                    $content[$item['type']] = [];
                }
                $content[$item['type']][] = $item['data'];
            }
            // 校验文本
            if (!empty($content['text'])) {
                if (count($content['text']) > 20) {
                    return $this->message('2000', '内容超过20条文本内容');
                }
            }
            // 校验图片
            if (!empty($content['image'])) {
                if (count($content['image']) > 20) {
                    return $this->message('2000', '内容超过50张图片URL');
                }
                foreach ($content['image'] as $item) {
                    if (mb_strlen($item) > 512) {
                        return $this->message('2000', '内容超过单张图片URL512字符');
                    }
                }
            }
            // 校验音频
            if (!empty($content['audio'])) {
                if (count($content['audio']) > 5) {
                    return $this->message('2000', '内容超过5条音频URL');
                }
                foreach ($content['audio'] as $item) {
                    if (mb_strlen($item) > 512) {
                        return $this->message('2000', '内容超过单条音频URL512字符');
                    }
                }
            }
            // 校验视频
            if (!empty($content['audiovideo'])) {
                if (count($content['audiovideo']) > 5) {
                    return $this->message('2000', '内容超过5条音视频URL');
                }
                foreach ($content['audiovideo'] as $item) {
                    if (mb_strlen($item) > 512) {
                        return $this->message('2000', '内容超过单条音视频URL512字符');
                    }
                }
            }
            // 校验文件
            if (!empty($content['file'])) {
                if (count($content['file']) > 10) {
                    return $this->message('2000', '内容超过10个文件URL');
                }
                foreach ($content['file'] as $item) {
                    if (mb_strlen($item) > 512) {
                        return $this->message('2000', '内容超过单个文件512字符');
                    }
                }
            }
        }
        return $this->message('0000', '校验成功');
    }

    /**
     * 查询接口
     * @param array|string taskIds
     * @return array
     */
    public function mediaCallbackQuery($taskIds)
    {
        $uri = 'https://as.dun.163.com/v2/mediasolution/callback/query';
        $params['version'] = 'v2';
        if (is_string($taskIds)) {
            $taskIds = explode(',', $taskIds);
        }
        if (count($taskIds) > 100) {
            return $this->message('2000', '单次查询支持最多查询100条数据');
        }
        $params['taskIds'] = $taskIds;
        $resp = $this->httpPost($uri, ['form_params' => $params]);
        if ($resp['code'] != 200) {
            return $this->message('2000', '查询失败', $resp['data'] ?? '');
        }
        return $this->message('0000', '查询成功', $resp['result']);
    }

    /**
     * 轮询模式
     * @param array $params
     * @return array
     */
    public function mediaCallbackResults($params)
    {
        $uri = 'https://as.dun.163.com/v2/mediasolution/callback/results';
        $params['version'] = 'v2';
        $resp = $this->httpPost($uri, ['form_params' => $params]);
        if ($resp['code'] != 200) {
            return $this->message('2000', '查询失败', $resp['data'] ?? '');
        }
        return $this->message('0000', '查询成功', $resp['result']);
    }

    /**
     * 通过Code获取失败原因
     * @param array|int $params
     * @return array
     */
    public function mediaFailureReasonByCode($code)
    {
        $reasonMap = [
            //音视频
            1 => '文件格式错误',
            2 => '文件下载失败',
            3 => '解析失败',
            4 => '音频流不存在',
            //视频
            110 => '请求重复',
            120 => '参数错误',
            130 => '解析错误',
            140 => '数据类型错误',
            160 => '视频大小超限（>5G）',
            //图片
            610 => '图片下载失败',
            620 => '图片格式错误',
            630 => '其他',
            //文档
            1000 => '文档大小超过上限',
            1001 => '文档格式不支持',
            1002 => '文档下载失败',
            1004 => '文件数超限',
            2000 => '文档内容提取失败',
            2001 => '文档内容提取超时',
            2002 => '内容加密',
            3000 => '文档检测失败',
            3001 => '文档文本检测失败',
            3002 => '文档图片检测失败',
            3003 => '检测超时',
        ];
        $failureReason = [];
        if (empty($code)) {
            return $failureReason;
        }
        $code = Arr::wrap($code);
        foreach ($code as $item) {
            if (!isset($reasonMap[$item])) {
                continue;
            }
            $failureReason[] = $reasonMap[$item];
        }
        return $failureReason;
    }

    /**
     * 融媒体结果处理
     * @param array $params
     * @return array
     */
    public function mediaCallbackCovert($callbackData)
    {
        // 处理通用结构
        $antispam = $callbackData['antispam'] ?? [];
        if (empty($antispam)) {
            return $this->message('2000', '参数错误');
        }
        if (empty($antispam['checkStatus']) || $antispam['checkStatus'] == 1) {
            return $this->message('2000', '检测进行中,task_id:' . $antispam['taskId']);
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
            $data['failure_reason'] = $this->mediaFailureReasonByCode($code);
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
                if (empty($oneEvidence['suggestion'])) {
                    continue;
                }
                //
                $data['suggestion'] = $oneEvidence['suggestion'] > $data['suggestion'] ? $oneEvidence['suggestion'] : $data['suggestion'];

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
                            $commonData['failure_reason'] = implode(',', $this->mediaFailureReasonByCode($oneEvidence['failureReason'] ?? 0));
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
                        'duration' => bcdiv($oneEvidence['duration'] ?? 0, 1000, 2),
                        'failure_reason' => '',
                    ];
                    if ($commonData['status'] == 3) {
                        $commonData['failure_reason'] = implode(',', $this->mediaFailureReasonByCode($oneEvidence['failureReason'] ?? 0));
                    }
                    foreach ($oneEvidence['segments'] ?? [] as $segment) {
                        $labels = $this->covertLabels('audio', $segment['labels'] ?? [],
                            ['start_time' => $segment['startTime'] ?? 0, 'end_time' => $segment['endTime'] ?? 0]);
                        $data['evidences'][$field][] = array_merge($commonData, ['labels' => $labels]);
                    }
                }
                if ($oneType == 'audiovideos') {
                    $commonData = [
                        'data_id' => $oneEvidence['dataId'] ?? '',
                        'status' => $oneEvidence['status'] ?? 2,
                        'duration' => bcdiv($oneEvidence['duration'] ?? 0, 1000, 2),
                        'failure_reason' => '',
                    ];
                    if ($commonData['status'] == 3) {
                        $commonData['failure_reason'] = implode(',', $this->mediaFailureReasonByCode($oneEvidence['failureReason'] ?? 0));
                    }
                    foreach ($oneEvidence['evidences'] ?? [] as $secondType => $secondEvidence) {
                        if (empty($secondEvidence['suggestion'])) {
                            continue;
                        }
                        $commonData['type'] = $secondType;
                        $commonData['suggestion'] = $secondEvidence['suggestion'];
                        foreach ($secondEvidence[($secondType == 'audio' ? 'segments' : 'pictures')] ?? [] as $segment) {
                            $labels = $this->covertLabels($commonData['type'], $segment['labels'] ?? [],
                                ['start_time' => $segment['startTime'] ?? 0, 'end_time' => $segment['endTime'] ?? 0]);
                            $data['evidences'][$field][] = array_merge($commonData, ['labels' => $labels]);
                        }
                    }
                }
                if ($oneType == 'files') {
                    foreach ($oneEvidence['evidences'] ?? [] as $secondType => $secondEvidences) {
                        if (!in_array($secondType, ['texts', 'images'])) {
                            continue;
                        }
                        $commonData = [
                            'type' => $secondType == 'texts' ? 'text' : 'image',
                            'data_id' => $oneEvidence['dataId'] ?? '',
                        ];
                        if ($commonData['type'] == 'image') {
                            $commonData['status'] = $oneEvidence['status'] ?? 2;
                            $commonData['failure_reason'] = '';
                            if ($commonData['status'] == 3) {
                                $commonData['failure_reason'] = implode(',', $this->mediaFailureReasonByCode($oneEvidence['failureReason'] ?? 0));
                            }
                        }
                        foreach ($secondEvidences as $secondEvidence) {
                            if (empty($secondEvidence['suggestion'])) {
                                continue;
                            }
                            $commonData['suggestion'] = $secondEvidence['suggestion'];
                            if ($commonData['type'] == 'image') {
                                $commonData['url'] = $secondEvidence['imageUrl'] ?? '';
                            }
                            $labels = $this->covertLabels($commonData['type'], $secondEvidence['labels'] ?? [], ['page' => $secondEvidence['page'] ?? 0]);
                            $data['evidences'][$field][] = array_merge($commonData, ['labels' => $labels]);
                        }
                    }
                }
            }
        }
        return $this->message('0000', '成功', $data);
    }



    private function covertLabels($type, $labels, $appendParams = [])
    {
        $data = [];
        foreach ($labels as $label) {
            if (empty($label['label'])) {
                continue;
            }
            if (empty($label['subLabels'])) {
                continue;
            }
            if (empty($label['level'])) {
                continue;
            }
            foreach ($label['subLabels'] as $subLabel) {
                if (empty($subLabel['subLabel'])) {
                    continue;
                }
                foreach ($subLabel['details']['hitInfos'] ?? [] as $hitInfo) {
                    $item = [
                        "label" => $label['label'],
                        "sub_label" => $subLabel['subLabel'],
                        "level" => $label['level'],
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
                        $item['page'] = $appendParams['page'] ?? 0;
                    } else if ($type == 'image') {
                        if (isset($hitInfo['x1'])) {
                            $item['x1'] = $hitInfo['x1'] ?? 0;
                        }
                        if (isset($hitInfo['x2'])) {
                            $item['x2'] = $hitInfo['x2'] ?? 0;
                        }
                        if (isset($hitInfo['y1'])) {
                            $item['y1'] = $hitInfo['y1'] ?? 0;
                        }
                        if (isset($hitInfo['y2'])) {
                            $item['y2'] = $hitInfo['y2'] ?? 0;
                        }
                        $item['page'] = $appendParams['page'] ?? 0;
                    } else if ($type == 'audio') {
                        $item['start_time'] = $appendParams['start_time'] ?? 0;
                        $item['end_time'] = $appendParams['end_time'] ?? 0;
                    } else if ($type == 'video') {
                        if (isset($hitInfo['x1'])) {
                            $item['x1'] = $hitInfo['x1'] ?? 0;
                        }
                        if (isset($hitInfo['x2'])) {
                            $item['x2'] = $hitInfo['x2'] ?? 0;
                        }
                        if (isset($hitInfo['y1'])) {
                            $item['y1'] = $hitInfo['y1'] ?? 0;
                        }
                        if (isset($hitInfo['y2'])) {
                            $item['y2'] = $hitInfo['y2'] ?? 0;
                        }
                        $item['start_time'] = bcdiv($appendParams['start_time'] ?? 0, 1000, 2);
                        $item['end_time'] = bcdiv($appendParams['end_time'] ?? 0, 1000, 2);
                    }
                    $data[] = $item;
                }
                // 没有命中词
                if (empty($subLabel['details']['hitInfos'])) {
                    $item = [
                        "label" => $label['label'],
                        "sub_label" => $subLabel['subLabel'],
                        "level" => $label['level'],
                        "value" => ''
                    ];
                    if ($type == 'audio') {
                        $item['start_time'] = $appendParams['start_time'] ?? 0;
                        $item['end_time'] = $appendParams['end_time'] ?? 0;
                    }
                    if ($type == 'video') {
                        $item['start_time'] = bcdiv($appendParams['start_time'] ?? 0, 1000, 2);
                        $item['end_time'] = bcdiv($appendParams['end_time'] ?? 0, 1000, 2);
                    }
                    $data[] = $item;
                }
            }
        }
        return $data;
    }
}
