<?php

namespace io\curl;

class CURL
{
    private $handle;

    public function __construct()
    {
        $this->handle = curl_init();
    }

    public function setOption($option, $value)
    {
        $this->checkState();
        curl_setopt($this->handle, $option, $value);
    }

    public function exec($url)
    {
        $this->checkState();
        $this->setOption(CURLOPT_URL, $url);
        $response = curl_exec($this->handle);
        return new CURLResponse(/*curl_copy_handle*/($this->handle), $response);
    }

    private function checkState()
    {
        if ($this->handle === null) {
            throw new Exception("Curl object was closed");
        }
    }

    public function close()
    {
        if (!$this->handle) {
            return;
        }
        curl_close($this->handle);
        $this->handle = null;
    }
}
