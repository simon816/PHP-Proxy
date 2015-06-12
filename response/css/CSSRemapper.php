<?php

namespace response\css;

use io\URL;
use io\URLUtils;

use response\ResponseHandler;
use response\HttpResponse;

class CSSRemapper implements ResponseHandler
{
    public function __construct(\ProxyURLRemapper $remapper, URL $baseUrl)
    {
        $this->parser = new CSSParser();
        $this->remapper = $remapper;
        $this->baseUrl = $baseUrl;
    }

    public function handleResponse(HttpResponse $response)
    {
        $headers = $response->getHeaders();
        if (!$headers->hasHeader('Content-Type') || stripos($headers->getFirstHeader('Content-Type'), 'text/css') === false) {
            return;
        }

        $response->setBody($this->remapCss($response->getBody()));
    }

    public function remapInline($inlineCss)
    {
        $index = $this->parser->parseCss("inline{{$inlineCss};}");
        $this->traverseCss($index);
        $section = $this->parser->getCSSArray($index, '__root__')['inline'];
        return implode(';', array_map(function ($key) use ($section) {
            return "{$key}:{$section[$key]}";
        }, array_keys($section)));
    }

    public function remapCss($css)
    {
        $index = $this->parser->parseCss($css);
        $this->traverseCss($index);
        return $this->parser->exportStyle($index);
    }

    private function traverseCss($index)
    {
        foreach ($this->parser->getMediaList($index) as $media) {
            foreach ($this->parser->getCSSArray($index, $media) as $selector => $rules) {
                $this->remapCssProperties($index, $media, $selector, $rules);
            }
        }
    }

    private function remapCssProperties($index, $media, $selector, array $properties)
    {
        foreach ($properties as $name => &$value) {
            try {
                $value = preg_replace_callback('/(.*url\()(.+?)(\).*)/i', function ($matches) {
                    return $matches[1] . $this->remapper->toRawUrl(URLUtils::inheritBase($this->baseUrl, $matches[2])) . $matches[3];
                }, $value, -1, $count);
                if ($count != 0) {
                    $this->parser->addProperty($index, $media, $selector, $name, $value);
                }
            } catch (\Exception $e) {
            }
        }
    }

}
