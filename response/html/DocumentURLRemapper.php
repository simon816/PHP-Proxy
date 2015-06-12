<?php

namespace response\html;

use io\URL;
use io\URLUtils;

use response\css\CSSRemapper;

class DocumentURLRemapper implements DOMHandler
{
    private $srcUrl;
    private $remapper;
    private $cssRemapper = null;

    public function __construct(URL $srcUrl, \ProxyURLRemapper $remapper)
    {
        $this->srcUrl = $srcUrl;
        $this->remapper = $remapper;
    }

    public function handleDocument(\DOMDocument $document)
    {
        if ($document->documentElement === null) {
            return;
        }
        $this->iterateElement($document->documentElement);
    }

    private function iterateElement(\DOMElement $element)
    {
        if ($element->childNodes == null) {
            return;
        }
        foreach ($element->childNodes as $child) {
            if (!($child instanceof \DOMElement)) {
                continue;
            }
            if ($child->hasAttributes()) {
                $hrefAttr = $child->attributes->getNamedItem('href');
                if ($hrefAttr != null) {
                    $href = $this->fullUrl($hrefAttr->value);
                    if ($child->tagName == 'a' || $child->tagName == 'button') {
                        if (strpos($hrefAttr->value, 'javascript:') === 0) {
                            continue;
                        }
                        if ($child->tagName == 'a' && strpos($hrefAttr->value, '#') === 0) {
                            continue;
                        }
                        $href = $this->remapper->toPageUrl($href);
                    } else {
                        $href = $this->remapper->toRawUrl($href);
                    }
                    $child->setAttribute('href', $href);
                }

                $srcAttr = $child->attributes->getNamedItem('src');
                if ($srcAttr != null) {
                    if ($child->tagName == 'img') {
                        if (strpos($srcAttr->value, 'data:') === 0) {
                            continue;
                        }
                    }
                    $src = $this->fullUrl($srcAttr->value);
                    if ($child->tagName == 'iframe') {
                        $src = $this->remapper->toPageUrl($src, 'noHeader');
                    } else {
                        $src = $this->remapper->toRawUrl($src);
                    }
                    $child->setAttribute('src', $src);
                }

                $styleAttr = $child->attributes->getNamedItem('style');
                if ($styleAttr != null) {
                    if ($this->cssRemapper != null) {
                        $child->setAttribute('style', $this->cssRemapper->remapInline($styleAttr->value));
                    }
                }
            }

            if ($child->tagName == 'meta') {
                $equivAttr = $child->attributes->getNamedItem('http-equiv');
                if ($equivAttr != null && $equivAttr->value == 'refresh') {
                    $contentAttr = $child->attributes->getNamedItem('content');
                    if ($contentAttr != null) {
                        $content = $contentAttr->value;
                        // TODO Improve this
                        if (($urlPos = stripos($content, 'url=')) !== false) {
                            $url = trim(substr($content, $urlPos + 4));
                            $url = $this->remapper->encodeUrl($this->fullUrl($url));
                            $content = substr($content, 0, $urlPos + 4) . $url;
                        }
                        $child->setAttribute('content', $content);
                    }
                }
            } elseif ($child->tagName == 'form') {
                $actionAttr = $child->attributes->getNamedItem('action');
                if ($actionAttr != null) {
                    $hiddenUrl = $element->ownerDocument->createElement('input');
                    $hiddenUrl->setAttribute('type', 'hidden');
                    $hiddenUrl->setAttribute('name', '__formurl__');
                    $hiddenUrl->setAttribute('value', $this->remapper->encodeUrl($this->fullUrl($actionAttr->value)));
                    $child->setAttribute('action', $this->remapper->getFormActionUrl());
                    $child->appendChild($hiddenUrl);
                }
            } elseif ($child->tagName == 'style' && $this->cssRemapper != null) {
                $css = $child->textContent;
                while ($child->firstChild) {
                    $child->removeChild($child->firstChild);
                }
                $newCss = $this->cssRemapper->remapCss($css);
                $child->appendChild($element->ownerDocument->createTextNode($newCss));
            }
            $this->iterateElement($child);
        }
    }

    private function fullUrl($attrUrl)
    {
        try {
            return (string) URLUtils::inheritBase($this->srcUrl, $attrUrl);
        } catch (\Exception $ignored) {
            return $attrUrl;
        }
    }

    public function setCssRemapper(CSSRemapper $remapper)
    {
        $this->cssRemapper = $remapper;
    }
}
