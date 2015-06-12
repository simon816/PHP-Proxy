<?php

namespace response\html;

use response\ResponseHandler;
use response\HttpResponse;

class HTMLPageObfuscator implements ResponseHandler {

    public function handleResponse(HttpResponse $resp)
    {
        $headers = $resp->getHeaders();
        if (!$headers->hasHeader('Content-Type') || stripos($headers->getFirstHeader('Content-Type'), 'text/html') === false) {
            return;
        }

        $content = $resp->getBody();

        $page = rawurlencode(strrev($content));
        $resp->setBody($this->makeJS($page, strlen($content) - 1));
    }

    private function makeJS(&$body, $i)
    {
        $js = "var o=unescape(\"{$body}\");var u='';for(var i={$i};i>=0;i--)u+=o[i];document.write(u);";
        $html = "<html><head><script>{$js}</script></head></html>";
        return $html;
    }
}
