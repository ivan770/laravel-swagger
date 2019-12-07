<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Basic Info
    |--------------------------------------------------------------------------
    |
    | The basic info for the application such as the title description,
    | description, version, etc...
    |
    */

    'title' => env('APP_NAME'),

    'description' => '',

    'appVersion' => '1.0.0',

    'host' => env('APP_URL'),

    'basePath' => '/',

    'schemes' => [
        // 'http',
        // 'https',
    ],

    'consumes' => [
        // 'application/json',
    ],

    'produces' => [
        // 'application/json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore methods
    |--------------------------------------------------------------------------
    |
    | Methods in the following array will be ignored in the paths array
    |
    */

    'ignoredMethods' => [
        'head',
    ],

    /*
    |--------------------------------------------------------------------------
    | Parse summary and descriptions
    |--------------------------------------------------------------------------
    |
    | If true will parse the action method docBlock and make it's best guess
    | for what is the summary and description. Usually the first line will be
    | used as the route's summary and any paragraphs below (other than
    | annotations) will be used as the description. It will also parse any
    | appropriate annotations, such as @deprecated.
    |
    */

    'parseDocBlock' => true,

    /*
    |--------------------------------------------------------------------------
    | Base responses
    |--------------------------------------------------------------------------
    |
    | Base responses are attached to every path
    |
    */

    'baseResponses' => [
        200 => ['description' => 'OK']
    ],

    /*
    |--------------------------------------------------------------------------
    | Modified responses
    |--------------------------------------------------------------------------
    |
    | Modified responses are attached to every POST, PUT, PATCH, DELETE path
    |
    */

    'modResponses' => [
        201 => ['description' => 'OK']
    ],

    /*
    |--------------------------------------------------------------------------
    | Guess tag by URL
    |--------------------------------------------------------------------------
    |
    | Should package use path prefix as tag
    |
    */

    'guess_tag' => false,

    /*
    |--------------------------------------------------------------------------
    | Models namespace
    |--------------------------------------------------------------------------
    |
    | Model classes namespace
    |
    */

    'model_namespace' => 'App\\Models\\'
];