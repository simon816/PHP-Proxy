<?php

use io\URL;
use io\curl\CURL;
use io\http\HeaderCollection;

use response\ResponseHandler;
use response\HttpResponse;

class Proxy
{
    private $respHandlers = array();
    private $options = array();

    const OPT_USERAGENT = 'userAgent';
    const OPT_TIMEOUT = 'timeout';
    const OPT_CERTS = 'caInfo';

    public function __construct()
    {
        $this->setOption(self::OPT_TIMEOUT, 10);
    }

    public function setOption($key, $agent)
    {
        $this->options[$key] = $agent;
    }

    private function newCurlInstance()
    {
        $curl = new Curl();
        $curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $curl->setOption(CURLOPT_HEADER, true);
        $curl->setOption(CURLOPT_TIMEOUT, $this->options[self::OPT_TIMEOUT]);
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, $this->options[self::OPT_TIMEOUT]);
        if (isset($this->options[self::OPT_USERAGENT])) {
            $curl->setOption(CURLOPT_USERAGENT, $this->options[self::OPT_USERAGENT]);
        }
        if (isset($this->options[self::OPT_CERTS])) {
            $curl->setOption(CURLOPT_CAINFO, $this->options[self::OPT_CERTS]);
        }
        return $curl;
    }

    private function parseHeaders($rawHeaders)
    {
        $headers = array();
        foreach (explode("\r\n", $rawHeaders) as $header) {
            if (!$header) {
                continue;
            }
            $pair = explode(': ', $header, 2);
            if (count($pair) == 1) {
                $value = $pair[0];
                $key = null;
            } else {
                $key = $pair[0];
                $value = $pair[1];
            }
            if (!array_key_exists($key, $headers)) {
                $headers[$key] = array();
            }
            $headers[$key][] = $value;
        }
        return $headers;
    }

    public function registerResponseHandler(ResponseHandler $handler)
    {
        $this->respHandlers[] = $handler;
    }

    public function proxyURL(URL $url, $data = null, $headers = array())
    {
        $curl = $this->newCurlInstance();
        if ($data !== null) {
            $curl->setOption(CURLOPT_POST, true);
            $curl->setOption(CURLOPT_POSTFIELDS, $data);
        }
        if (count($headers) > 0) {
            $curl->setOption(CURLOPT_HTTPHEADER, $headers);
        }
        $resp = $curl->exec((string) $url);
        $curl->close();
        $headerArray = $this->parseHeaders($resp->getRawHeaders());
        $headers = new HeaderCollection($headerArray);
        $text = $resp->getResponseText();
        if ($headers->hasHeader('Content-Type') && stripos($headers->getFirstHeader('Content-Type'), 'text/') !== false) {
            $text = utf8_decode($text);
        }
        $output = new HttpResponse($headers, $text);
        foreach ($this->respHandlers as $handler) {
            $handler->handleResponse($output);
        }
        return $output;
    }
}
