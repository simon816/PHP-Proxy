<?php

namespace io\http;

class HeaderCollection
{
    private $headers;

    public function __construct(array $headers)
    {
        $this->headers = array();
        foreach ($headers as $key => $header) {
            $this->headers[strtolower($key)] = $header;
        }
    }

    public function hasHeader($key)
    {
        return array_key_exists(strtolower($key), $this->headers);
    }

    public function getHeader($key)
    {
        if ($this->hasHeader($key)) {
            return $this->headers[strtolower($key)];
        }
        return null;
    }

    public function getFirstHeader($key)
    {
        $header = $this->getHeader($key);
        if ($header == null || count($header) < 1) {
            return null;
        }
        return $header[0];
    }

    public function getAllKeys()
    {
        return array_keys($this->headers);
    }

    public function setHeader($key, array $values)
    {
        return new self(array_merge($this->headers, array($key => $values)));
    }
}
