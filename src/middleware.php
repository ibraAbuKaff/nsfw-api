<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);


$app->add(function ($request, $response, $next) {

    $requests = getenv('MAX_REQUEST'); // maximum number of requests
    $inmins   = getenv('MAX_REQUEST_PER_MIN');    // in how many time (minutes)

    $APIRateLimit    = new \Src\Models\APIRateLimit($requests, $inmins);
    $mustbethrottled = $APIRateLimit();

    if ($mustbethrottled == false) {
        $responsen = $next($request, $response);
    } else {
        $responsen = $response->withStatus(429)
                              ->withHeader('RateLimit-Limit', $requests);
    }

    return $responsen;
}
);