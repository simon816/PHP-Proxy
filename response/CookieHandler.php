<?php

namespace response;

use io\URL;

class CookieHandler implements ResponseHandler
{
    private $requestUrl;
    private $defaultCookie;
    private $sessionCookie;
    private $cookiesToDelete;

    public function __construct(URL $proxyUrl, URL $requestUrl)
    {
        $this->requestUrl = $requestUrl;
        $this->defaultCookie = array(
            'domain' => $requestUrl->getHost(),
            'expires' => 0,
            'httponly' => false,
            'secure' => false,
            'path' => dirname($requestUrl->getPath())
        );
        $this->loadSessionCookie();
        $this->cookiesToDelete = $_COOKIE;
        unset($this->cookiesToDelete['PROXYSESSION']);
    }

    private function loadSessionCookie()
    {
        $session = session_get_cookie_params();
        $session['expires'] = $session['lifetime'];
        $session['name'] = session_name();
        $session['value'] = session_id();
        $this->sessionCookie = new Cookie($session);
    }

    public function getCookieHeader()
    {
        if (!isset($_SESSION['cookies'])) {
            return null;
        }
        $header = array();
        foreach ($_SESSION['cookies'] as $id => $data) {
            $cookie = new Cookie($data);
            if ($cookie->hasExpired()) {
                unset($_SESSION['cookies'][$id]);
                continue;
            }
            if (!$cookie->acceptsUrl($this->requestUrl)) {
                continue;
            }
            if (!$cookie->isHttpOnly()) {
                if (!isset($_COOKIE[$cookie->getName()])) {
                    // The client removed the cookie
                    unset($_SESSION['cookies'][$id]);
                    continue;
                }
                $cookie->setValue($_COOKIE[$cookie->getName()]);
                unset($_COOKIE[$cookie->getName()]);
            }
            $header[] = $cookie->asCookieHeader();
        }
        foreach ($_COOKIE as $name => $value) {
            if ($name != session_name()) {
                $header[] = $name . '=' . $value;
            }
        }
        if (count($header) == 0) {
            return null;
        }
        return implode(';', $header);
    }

    public function handleResponse(HttpResponse $response)
    {
        $headers = $response->getHeaders();
        $rawCookies = $headers->getHeader('Set-Cookie');
        if ($rawCookies == null) {
            $rawCookies = array();
        }
        if (!isset($_SESSION['cookies'])) {
            $_SESSION['cookies'] = array();
        }
        $clientCookies = array();
        $deleted = $this->cookiesToDelete;
        foreach ($rawCookies as $rawCookie) {
            if ($rawCookie = trim($rawCookie)) {
                $cookie = $this->parseCookie($rawCookie);
                if ($cookie != null && $cookie->acceptsUrl($this->requestUrl)) {
                    $_SESSION['cookies'][$cookie->getId()] = $cookie->getData();
                    if (!$cookie->isHttpOnly()) {
                        // Some JS may need to access the cookie
                        // Cookies must use the same expiry etc as session
                        $newData = $this->sessionCookie->getData();
                        $newData['name'] = $cookie->getName();
                        $newData['value'] = $cookie->getValue();
                        $newData['httponly'] = false;
                        $newCookie = new Cookie($newData);
                        $clientCookies[$newCookie->getId()] = $newCookie->asSetCookie();
                        unset($deleted[$newCookie->getName()]);
                    }
                }
            }
        }
        foreach (array_keys($deleted) as $cookieName) {
            $clientCookies[$cookieName] = "{$cookieName}=deleted; expires=" . gmdate('D, d-M-Y H:i:s T',  time() - 60 * 60 * 24 * 365);
        }
        $headers = $headers->setHeader('Set-Cookie', array_values($clientCookies));
        $response->setHeaders($headers);
    }

    private function parseCookie($cookieString)
    {
        $cookie = $this->defaultCookie;
        foreach (explode(';', $cookieString) as $term) {
            $keyVal = explode('=', $term);
            $key = trim($keyVal[0]);
            $value = null;
            if (count($keyVal) > 1) {
                $value = trim(implode('=', array_splice($keyVal, 1)));
            }
            if (in_array(strtolower($key), array('domain', 'expires', 'path', 'secure', 'httponly'))) {
                $key = strtolower($key);
                if ($key == 'expires') {
                    $value = strtotime($value);
                } elseif ($key == 'secure' || $key == 'httponly') {
                    $value = true;
                } elseif ($key == 'domain' && $value !== null && $value{0} != '.') {
                    $value = '.' . $value;
                }
                $cookie[$key] = $value;
            } elseif (!isset($cookie['name'])) {
                // For now, only a single key/value is supported
                $cookie['name'] = $key;
                $cookie['value'] = $value;
            }
        }
        if (!isset($cookie['name'])) {
            return null;
        }
        return new Cookie($cookie);
    }
}
