<?php

namespace io\curl;

class CURLResponse
{
    private $handle;
    private $respText;

    public function __construct($handle, $responseText)
    {
        $this->handle = $handle;
        $headerSize = $this->getInfo(CURLINFO_HEADER_SIZE);
        $this->headers = substr($responseText, 0, $headerSize);
        $this->respText = substr($responseText, $headerSize);
    }

    public function getInfo($key)
    {
        return curl_getinfo($this->handle, $key);
    }

    public function getRawHeaders()
    {
        return $this->headers;
    }

    public function getResponseText()
    {
        return $this->respText;
    }
}
