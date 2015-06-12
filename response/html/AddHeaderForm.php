<?php

namespace response\html;

use io\URL;

class AddHeaderForm implements DOMHandler
{
    public function __construct(URL $formActionUrl, URL $currentUrl, $cookiePath)
    {
        $this->action = $formActionUrl;
        $this->currentUrl = $currentUrl;
        $this->cookiePath = $cookiePath;
    }

    public function handleDocument(\DOMDocument $document)
    {
        if ($document->documentElement === null) {
            return;
        }

        $bodyElems = $document->documentElement->getElementsByTagName('body');
        if ($bodyElems->length == 0) {
            $body = $document->createElement('body');
            $document->documentElement->appendChild($body);
        } else {
            $body = $bodyElems->item(0);
        }

        $body->insertBefore($this->createHeaderForm($document), $body->firstChild);

        $headElems = $document->documentElement->getElementsByTagName('head');
        if ($headElems->length == 0) {
            $head = $document->createElement('head');
            $document->documentElement->insertBefore($head, $body);
        } else {
            $head = $headElems->item(0);
        }

        $head->insertBefore($this->createJSHeader($document), $head->firstChild);
    }

    private function createHeaderForm(\DomDocument $document)
    {
        $form = $document->createElement('form');
        $form->setAttribute('action', $this->action);
        $form->setAttribute('method', 'POST');
        $form->setAttribute('style', 'z-index:99999999;position:absolute');

        $urlInput = $document->createElement('input');
        $urlInput->setAttribute('type', 'text');
        $urlInput->setAttribute('name', 'url');
        $urlInput->setAttribute('value', $this->currentUrl);
        $urlInput->setAttribute('size', '100');
        $form->appendChild($urlInput);

        $submit = $document->createElement('input');
        $submit->setAttribute('type', 'submit');
        $submit->setAttribute('value', 'Go');
        $form->appendChild($submit);

        return $form;
    }

    private function createJSHeader(\DomDocument $document)
    {
        // TODO This hack probably shouldn't belong here
        $script = $document->createElement('script');
        $js = <<<JS
try {(function(document) {
    var cookieProperty = Object.getOwnPropertyDescriptor(document, 'cookie');
    if (!cookieProperty) {
        cookieProperty = {
            'get': document.__lookupGetter__('cookie'),
            'set': document.__lookupSetter__('cookie')
        }
    }
    Object.defineProperty(document, 'cookie', {
        'get': function () {
            return cookieProperty.get.call(document);
        },
        'set': function (newValue) {
            var kv = newValue.split(';');
            for (var i = 0, l = kv.length; i < l; i++) {
                if (kv[i].trim().indexOf('path') === 0) {
                    kv[i] = ' path={$this->cookiePath}';
                    break;
                }
            }
            cookieProperty.set.call(document, kv.join(';'));
        }
    });
}) (window.document);} catch (e) {};
JS;
        $script->appendChild($document->createTextNode(str_replace(array("\n", "\r", '  '), '', $js)));
        return $script;
    }
}
