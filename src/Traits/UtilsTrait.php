<?php

namespace Githen\LaravelYidun\Traits;

trait UtilsTrait
{

    /**
     * 将输入数据的编码统一转换成utf8
     * @params array 输入的参数
     * @return array
     * @author nanjishidu
     */
    function toUtf8($params)
    {
        $utf8s = array();
        foreach ($params as $key => $value) {
            $utf8s[$key] = is_string($value) ? mb_convert_encoding($value, "utf8", "auto") : $value;
        }
        return $utf8s;
    }

    /**
     * 生成签名信息
     * @param array $params
     * @return string
     * @author nanjishidu
     */
    public function genSignature($params)
    {
        ksort($params);
        $buff = "";
        foreach ($params as $key => $value) {
            $buff .= $key;
            $buff .= $value;
        }
        $buff .= $this->getSecretKey();
        return md5(mb_convert_encoding($buff, "utf8", "auto"));

    }

    /**
     * 生成OpenAi签名信息
     * @param array $params
     * @return string
     * @author nanjishidu
     */
    public function genOpenAiSignature($secretKey, $headers, $params)
    {
        ksort($params);
        $buff = "";
        foreach ($params as $key => $value) {
            $buff .= $key;
            $buff .= $value;
        }
        $buff .= $secretKey;
        $buff .= $headers['X-YD-NONCE']??'';
        $buff .= $headers['X-YD-TIMESTAMP']??'';
        return sha1(mb_convert_encoding($buff, "utf8", "auto"));

    }
}
