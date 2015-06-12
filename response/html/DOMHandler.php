<?php

namespace response\html;

interface DOMHandler
{
    public function handleDocument(\DOMDocument $document);
}
