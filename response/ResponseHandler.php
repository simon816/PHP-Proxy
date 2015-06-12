<?php

namespace response;

interface ResponseHandler
{
    public function handleResponse(HttpResponse $response);
}
