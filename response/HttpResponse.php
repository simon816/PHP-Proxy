<?php

namespace response;

use io\http\HeaderCollection;

class HttpResponse
{
    private $headers;
    private $body;

    public function __construct(HeaderCollection $headers, $body)
    {
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setHeaders(HeaderCollection $headers)
    {
        $this->headers = $headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }
}
