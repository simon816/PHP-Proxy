<?php

namespace response;

use io\URL;

class Cookie
{
    private $cookie;
    private $id;

    public function __construct(array $cookieData)
    {
        $this->cookie = $cookieData;
        $this->id = hash('sha1', hash('md5', $cookieData['domain']) . hash('md5', $cookieData['path']) . hash('md5', $cookieData['name']));
    }

    public function acceptsUrl(URL $url)
    {
        if ($this->cookie['secure'] && $url->getProtocol() !== 'https') {
            return false;
        }

        $domain = $this->cookie['domain'];

        if ($domain{0} != '.') {
            if ($url->getHost() !== $domain) {
                return false;
            }
        } else {
            $domain = substr($domain, 1);
        }

        if (strpos($url->getHost(), $domain) === false) {
            return false;
        }

        return strpos($url->getPath(), $this->cookie['path']) !== false;
    }

    public function asSetCookie()
    {
        $header = $this->cookie['name'] . '=' . $this->cookie['value'] . '; path=' . $this->cookie['path'];
        if ($this->cookie['domain']) {
            $header .= '; domain=' . $this->cookie['domain'];
        }
        if ($this->cookie['expires'] != 0) {
            $header .= '; expires=' . gmdate('D, d-M-Y H:i:s T', $this->cookie['expires']);
        }
        if ($this->cookie['secure']) {
            $header .= '; secure';
        }
        if ($this->cookie['httponly']) {
            $header .= '; httponly';
        }
        return $header;
    }

    public function asCookieHeader()
    {
        return $this->cookie['name'] . '=' . $this->cookie['value'];
    }

    public function getData()
    {
        return (array) clone (object) $this->cookie;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->cookie['name'];
    }

    public function getValue()
    {
        return $this->cookie['value'];
    }

    public function hasExpired()
    {
        return $this->cookie['expires'] == 0 ? false : $this->cookie['expires'] < time();
    }

    public function isHttpOnly()
    {
        return $this->cookie['httponly'];
    }

    public function setValue($newValue)
    {
        $this->cookie['value'] = $newValue;
    }
}
