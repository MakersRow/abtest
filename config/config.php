<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Experiments
    |--------------------------------------------------------------------------
    |
    | A list of experiment identifiers followed by its probability percentage
    | number.
    |
    | Note: The sum of all percentages should be between 99 and 100
    |
    | *Is highly recommended to reset the ab:report stats after any change to
    |  the experiments*
    |
    | Example: [
    |   'big-logo' => 60,
    |   'small-buttons' => 40
    |   ]
    |
    */
    'experiments' => [],
    /*
    |--------------------------------------------------------------------------
    | Goals
    |--------------------------------------------------------------------------
    |
    | A list of goals.
    |
    | Example: ['pricing/order', 'signup']
    |
    */
    'goals' => [],
    /*
    |--------------------------------------------------------------------------
    | Ignore Crawlers
    |--------------------------------------------------------------------------
    |
    | Ignore pageviews for crawlers.
    |
    */
    'ignore_crawlers' => false,
];
