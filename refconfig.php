<?php

return array(

    // initially expanded levels (for HTML mode only)
    'expLvl'               => 2,

    // depth limit (0 = no limit);
    // this is not related to recursion
    'maxDepth'             => 6,

    // display iterator contents
    'showIteratorContents' => false,

    // display extra information about resources
    'showResourceInfo'     => true,

    // display method and parameter list on objects
    'showMethods'          => true,

    // display private properties / methods
    'showPrivateMembers'   => false,

    // peform string matches (date, file, functions, classes, json, serialized data, regex etc.)
    // note: seriously slows down queries on large amounts of data
    'showStringMatches'    => true,

    // shortcut functions used to access the query method below;
    // if they are namespaced, the namespace must be present as well (methods are not supported)
    'shortcutFunc'         => array('r', 'rt'),

    // custom/external formatters (as associative array: format => className)
    'formatters'           => array(),

    // stylesheet path (for HTML only);
    // 'false' means no styles
    'stylePath'            => '{:dir}/ref.css',

    // javascript path (for HTML only);
    // 'false' means no js
    'scriptPath'           => '{:dir}/ref.js',
);
