<?php

namespace Githen\LaravelYidun;

use Illuminate\Support\Str;
use Githen\LaravelYidun\Traits\MediaTrait;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Client as GuzzleHttpClient;

class Client
{
    use MediaTrait;

    /**
     * The Secret Id
     */
    private $secretId = "";

    /**
     * The Secret Key
     */
    private $secretKey = "";

    /**
     * @return string
     */
    public function getSecretId(): string
    {
        return $this->secretId;
    }

    /**
     * @param string $secretId
     */
    public function setSecretId(string $secretId): void
    {
        $this->secretId = $secretId;
    }

    /**
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * @param string $secretKey
     */
    public function setSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }
    
    /**
     * Default constructor
     *
     * @param array $config Iflytek configuration data
     * @return void
     */
    public function __construct($config)
    {
        $this->setAppId($config['secret_id']);
        $this->setApiKey($config['secret_key']);
        return;
    }

    /**
     * 将输入数据的编码统一转换成utf8
     * @params array 输入的参数
     * @return array
     * @author nanjishidu
     */
    function toUtf8($params){
        $utf8s = array();
        foreach ($params as $key => $value) {
            $utf8s[$key] = is_string($value) ? mb_convert_encoding($value, "utf8", INTERNAL_STRING_CHARSET) : $value;
        }
        return $utf8s;
    }

    /**
     * 生成签名信息
     * @param string $apiURL
     * @return string
     * @author nanjishidu
     */
    public function genSignature($params)
    {
        ksort($params);
        $buff="";
        foreach($params as $key=>$value){
            $buff .=$key;
            $buff .=$value;
        }
        $buff .= $this->getSecretKey;
        return md5(mb_convert_encoding($buff, "utf8", "auto"));

    }

    /**
     * @param $uri
     * @param $options
     * @return array|mixed
     */
    public function httpPost($uri, $options = [])
    {
        $handlerStack = HandlerStack::create(new CurlHandler());
        $handlerStack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));
        $httpClient = new GuzzleHttpClient([
            'timeout' => 10,
            'handler' => $handlerStack,
        ]);
        // 请求公共参数
        if (!empty($options)){
            $options['form_params'] = [];
        }
        $options['form_params']['secretId'] = $this->getSecretId();
        $options['form_params']['timestamp'] = time() * 1000; 
        $options['form_params']['nonce'] = sprintf("%d", rand());;
        $options['form_params']['signatureMethod'] = 'md5';
        $options['form_params']['signature'] = $this->genSignature($this->toUtf8($options['form_params']));
        try {
            $response = $httpClient->request('POST',
                $uri,
                $options);
            $content = $response->getBody()->getContents();
            return json_decode($content, true);
        } catch (\Exception $e) {
            return $this->message($e->getCode(), $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * 最大重试次数
     */
    const MAX_RETRIES = 3;

    /**
     * 返回一个匿名函数, 匿名函数若返回false 表示不重试，反之则表示继续重试
     * @return \Closure
     */
    private function retryDecider()
    {
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) {
            // 超过最大重试次数，不再重试
            if ($retries >= self::MAX_RETRIES) {
                return false;
            }

            // 请求失败，继续重试
            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response) {
                // 如果请求有响应，但是状态码不等于200，继续重试
                if ($response->getStatusCode() != 200) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * 返回一个匿名函数，该匿名函数返回下次重试的时间（毫秒）
     * @return \Closure
     */
    private function retryDelay()
    {
        return function ($numberOfRetries) {
            return 1000 * $numberOfRetries;
        };
    }

    /**
     * 封装消息
     * @param string $code
     * @param string $message
     * @param array $data
     * @return array
     */
    private function message($code, $message, $data = [])
    {
        return ['code' => $code, 'message' => $message, 'data' => $data];
    }

}