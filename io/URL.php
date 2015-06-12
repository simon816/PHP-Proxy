<?php

namespace io;

final class URL
{
    private $scheme;
    private $host;
    private $port;
    private $path;
    private $query;
    private $fragment;

    public function __construct($spec)
    {
        try {
            $urlData = URLUtils::toPartialArray($spec);
            if (!isset($urlData['scheme'])) {
                throw new MalformedURLException("Protocol not specified");
            }
            if (!isset($urlData['host'])) {
                throw new MalformedURLException("Host not specified");
            }
        } catch (\ErrorException $e) {
            throw new MalformedURLException($e->getMessage());
        }
        $this->scheme = $urlData['scheme'];
        $this->host = $urlData['host'];
        $this->port = isset($urlData['port']) ? $urlData['port'] : -1;
        $this->path = isset($urlData['path']) ? $urlData['path'] : null;
        $this->query = isset($urlData['query']) ? $urlData['query'] : null;
        $this->fragment = isset($urlData['fragment']) ? $urlData['fragment'] : null;
    }

    public function getProtocol()
    {
        return $this->scheme;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getRef()
    {
        return $this->fragment;
    }

    public function getFile()
    {
        return $this->path . ($this->query ? '?' . $this->query : '');
    }

    public function __toString()
    {
        return $this->scheme . '://' . $this->host . ($this->port != -1 ? ':' . $this->port : '') . $this->getFile()
            . ($this->fragment ? '#' . $this->fragment : '');
    }
}
