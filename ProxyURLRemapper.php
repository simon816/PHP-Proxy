<?php

use io\URL;
use io\URLUtils;

class ProxyURLRemapper
{
    public function __construct(URL $rootUrl, $pageQuery, $rawQuery)
    {
        $this->root = $rootUrl;
        $this->pageQuery = $pageQuery;
        $this->rawQuery = $rawQuery;
    }

    public function toPageUrl($urlIn, $additional = '')
    {
        return URLUtils::appendQuery($this->root, array($this->pageQuery => $this->encodeUrl($urlIn)), $additional);
    }

    public function toRawUrl($urlIn)
    {
        return URLUtils::appendQuery($this->root, array($this->rawQuery => $this->encodeUrl($urlIn)));
    }

    public function getFormActionUrl()
    {
        return (string) $this->root;
    }

    public function encodeUrl($url)
    {
        return str_rot13(base64_encode($url));
    }

    public function decodeUrl($url)
    {
        return base64_decode(str_rot13($url));
    }
}
