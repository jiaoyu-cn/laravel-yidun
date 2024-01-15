<?php

namespace Githen\LaravelYidun\Traits;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Client as GuzzleHttpClient;

trait LabelTrait
{
    /**
     * https://support.dun.163.com/documents/588434277524447232?docId=444281309180616704
     * 获取AI内容安全审核接口返回的标签信息，客户可以通过轮询调用该接口获取数据。
     * @param array $params
     * @return array
     */
    public function labelQuery($secretId, $secretKey, array $params = [])
    {
        $uri = 'https://openapi.dun.163.com/openapi/v2/antispam/label/query';
        $handlerStack = HandlerStack::create(new CurlHandler());
        $handlerStack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));
        $httpClient = new GuzzleHttpClient([
            'timeout' => 10,
            'verify' => false,
            'handler' => $handlerStack,
        ]);
        // 请求公共参数
        $headers = [
            'X-YD-SECRETID' => $secretId,
            'X-YD-TIMESTAMP' => strval(time() * 1000),
            'X-YD-NONCE' => sprintf("%d", rand()),
        ];
        $headers['X-YD-SIGN'] = $this->genOpenAiSignature($secretKey, $headers, $params);
        try {
            $response = $httpClient->request('GET',
                $uri,
                [
                    'headers' => $headers,
                    'query' => $params,
                ]);
            $content = $response->getBody()->getContents();
            return json_decode($content, true);
        } catch (\Exception $e) {
            return $this->message($e->getCode(), $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }

}
