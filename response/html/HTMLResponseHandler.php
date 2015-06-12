<?php

namespace response\html;

use response\ResponseHandler;
use response\HttpResponse;

class HTMLResponseHandler implements ResponseHandler
{

    private $domHandlers = array();

    public function handleResponse(HttpResponse $resp)
    {
        $headers = $resp->getHeaders();
        if (!$headers->hasHeader('Content-Type') || stripos($headers->getFirstHeader('Content-Type'), 'text/html') === false) {
            return;
        }

        // Fix <script> elements - Remove contents
        list($text, $scripts) = $this->removeJS($resp->getBody());

        $document = new \DOMDocument();
        libxml_use_internal_errors(true); // Don't issue warnings everywhere
        if (!defined('LIBXML_HTML_NODEFDTD')) {
            define('LIBXML_HTML_NODEFDTD', 4);
        }
        if (!empty($text) && !$document->loadHTML($text, LIBXML_HTML_NODEFDTD)) {
            throw new \Exception("Unable to load HTML");
        }
        // Fix <script> elements - Add contents back
        $this->insertJS($document, $scripts);

        foreach ($this->domHandlers as $handler) {
            $handler->handleDocument($document);
        }
        $html = $document->saveHTML();
        if ($html == false) {
            throw new Exception("Unable to save HTML");
        }
        $resp->setBody($html);
    }

    private function removeJS($text)
    {
        $scripts = array();
        do {
            $matches = array();
            // Note: this is not perfect. If there is a literal '</script>' within the JS (i.e. in a string) it will mess up
            if (preg_match("'(<\s*script(?:(?:[^>]*[^/])|(?:\s*))>)(.*?)(<\s*/\s*script\s*>)'is", $text, $matches, PREG_OFFSET_CAPTURE)) {
                $text = substr($text, 0, $matches[1][1]) .  '%Script' . count($scripts) . 'Marker%' . substr($text, $matches[3][1] + strlen($matches[3][0]));
                $scripts[] = array($matches[1][0], $matches[2][0], $matches[3][0]);
            }
        } while (count($matches) > 0);
        foreach ($scripts as $i => $script) {
            $text = str_replace("%Script{$i}Marker%", $script[0] . $i . $script[2], $text);
        }
        return array($text, $scripts);
    }

    private function insertJS(\DOMDocument $document, array $scripts)
    {
        $scriptsElements = $document->getElementsByTagName('script');
        if ($scriptsElements->length != count($scripts)) {
            // Mismatch of <script> tags and scripts found with regex. Test comments
            $comments = (new \DOMXpath($document))->query('//comment()');
            foreach ($comments as $comment) {
                if (strlen($comment->data) < 10 || substr($comment->data, 0, 3) != '[if') {
                    continue;
                }
                if (!preg_match('/(\[if .+?\]>)(.+)(<!\[endif\])/i', $comment->data, $matches)) {
                    continue;
                }
                $tmpDoc = new \DOMDocument();
                if (!$tmpDoc->loadHTML($matches[2], LIBXML_HTML_NODEFDTD)) {
                    continue;
                }
                $commentScripts = $tmpDoc->getElementsByTagName('script');
                if ($commentScripts->length == 0) {
                    continue;
                }
                foreach ($commentScripts as $scriptElement) {
                    $i = (int) $scriptElement->textContent;
                    $scriptElement->removeChild($scriptElement->firstChild); // Remove index value
                    $scriptElement->appendChild($tmpDoc->createTextNode($scripts[$i][1]));
                }
                $newComment = str_replace(array('<html>', '</html>', '<head>', '</head>', '<body>', '</body>'), '', $tmpDoc->saveHTML());
                $comment->data = $matches[1] . $newComment . $matches[3];
            }
        }
        foreach ($scriptsElements as $scriptElement) {
            $i = (int) $scriptElement->textContent;
            $scriptElement->removeChild($scriptElement->firstChild); // Remove index value
            $scriptElement->appendChild($document->createTextNode($scripts[$i][1]));
        }
    }

    public function registerDomHandler(DOMHandler $handler) {
        $this->domHandlers[] = $handler;
    }
}
