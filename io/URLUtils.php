<?php

namespace io;

class URLUtils
{
    public static function appendQuery(URL $url)
    {
        $args = func_get_args();
        array_shift($args);
        if (count($args) == 0) {
            return $url;
        }
        $newQuery = self::queryAsArray($url->getQuery());
        foreach ($args as $queryPart) {
            if (!is_array($queryPart)) {
                $queryPart = self::queryAsArray($queryPart);
            }
            $newQuery = array_merge($newQuery, $queryPart);
        }
        return self::fromPartial($url, array('query' => self::queryAsString($newQuery)));
    }

    public static function queryAsString(array $query)
    {
        return http_build_query($query);
    }

    public static function queryAsArray($queryString)
    {
        parse_str($queryString, $output);
        return $output;
    }

    // Resolves the relative path ($target) with respect to the base path ($base)
    // E.g ('/abc', 'def') --> '/def'
    // ('foo/bar.htm', 'baz.htm') --> '/foo/baz.htm'
    // ('/some/thing', '/another/thing') --> '/another/thing'
    public static function mergePaths($base, $target)
    {
        if (strlen($target) == 0) {
            return '';
        }
        if (strlen($base) == 0) {
            return $target;
        }
        if ($target{0} == '/') {
            return $target;
        }
        if ($base{0} != '/') {
            $base = '/' . $base;
        }
        if ($base{strlen($base) - 1} != '/') {
            $base = dirname($base) . '/';
        }
        return $base . $target;
    }

    public static function toPartialArray($urlString, $sanitizeOnError = true)
    {
        $urlData = @parse_url($urlString);
        if (!is_array($urlData)) {
            if ($sanitizeOnError) {
                $sanitized = self::trySanitize($urlString);
                if ($sanitized != $urlString) {
                    return self::toPartialArray($sanitized, false);
                }
            }
            $error = error_get_last();
            throw new \ErrorException($error['message'], -1, $error['type'], $error['file'], $error['line']);
        }
        return $urlData;
    }

    // Sometimes the url query may contain invalid characters (e.g. ':') that fails parse_url.
    // This will safely encode them using urlencode. (so ':' becomes '%3A')
    private static function trySanitize($urlString)
    {
        $querySplit = explode('?', $urlString);
        if (count($querySplit) != 1) {
            $before = array_shift($querySplit);
            $query = implode('?', $querySplit);
            $fragSplit = explode('#', $query);
            $frag = '';
            if (count($fragSplit) != 1) {
                $query = array_shift($fragSplit);
                $frag = implode('#', $fragSplit);
                $frag = '#' . urlencode($frag);
            }
            $query = implode('&', array_map(function ($q) {
                if (count($split = explode('=', $q)) == 1) return $q;
                $k = array_shift($split);
                return $k . '=' . urlencode(implode('=', $split));
            }, explode('&', $query)));
            $urlString = $before . '?' . $query . $frag;
        }
        return $urlString;
    }

    public static function fromPartial(URL $base, array $partial)
    {
        $scheme = isset($partial['scheme']) ? $partial['scheme'] : $base->getProtocol();
        $host = isset($partial['host']) ? $partial['host'] : $base->getHost();
        $port = isset($partial['port']) ? $partial['port'] : $base->getPort();
        $path = isset($partial['path']) ? $partial['path'] : $base->getPath();
        $query = isset($partial['query']) ? $partial['query'] : $base->getQuery();
        $fragment = isset($partial['fragment']) ? $partial['fragment'] : $base->getRef();
        return self::urlOf($scheme, $host, $port, $path, $query, $fragment);
    }

    public static function urlOf($scheme, $host, $port, $path, $query = '', $fragment = '')
    {
        $spec = $scheme . '://' . $host . ($port != -1 ? ':' . $port : '') . $path . ($query ? '?' . $query : '') . ($fragment ? '#' . $fragment : '');
        return new URL($spec);
    }

    public static function completeUrl($partialUrl)
    {
        if (is_array($partialUrl)) {
            $urlData = $partialUrl;
        } else {
            $urlData = self::toPartialArray($partialUrl);
        }
        if (!isset($urlData['host']) && isset($urlData['path'])) {
            $slashPos = strpos($urlData['path'], '/');
            if ($slashPos !== false) {
                $urlData['host'] = substr($urlData['path'], 0, $slashPos);
                $urlData['path'] = substr($urlData['path'], $slashPos);
            } else {
                $urlData['host'] = $urlData['path'];
                unset($urlData['path']);
            }
        }
        $defaults = array('scheme' => 'http', 'port' => -1, 'path' => '/', 'query' => '', 'fragment' => '');
        $urlData = array_merge($defaults, $urlData);
        if (!isset($urlData['host'])) {
            throw new MalformedURLException("Host not specified");
        }
        return self::urlOf($urlData['scheme'], $urlData['host'], $urlData['port'], $urlData['path'], $urlData['query'], $urlData['fragment']);
    }

    public static function inheritBase(URL $base, $relPath)
    {
        $urlData = self::toPartialArray($relPath);
        if (!isset($urlData['scheme'])) {
            $urlData['scheme'] = $base->getProtocol();
        }
        if (!isset($urlData['host'])) {
            $urlData['host'] = $base->getHost();
        }
        if (isset($urlData['path'])) {
            $urlData['path'] = self::mergePaths($base->getPath(), $urlData['path']);
        }
        return self::completeUrl($urlData);
    }
} 
