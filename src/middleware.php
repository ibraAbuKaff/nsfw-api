<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);


$app->add(function ($request, $response, $next) {

    $requests = 500; // maximum number of requests
    $inmins   = 30;    // in how many time (minutes)

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