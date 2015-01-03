<?php
header('Content-Type: text/plain');

// Simple dump
function pre($input, $exit = false){
    printf("%s\n", print_r($input, 1));
    if ($exit) {
        exit;
    }
}
function prd($input, $exit = false){
    var_dump($input);
    if ($exit) {
        exit;
    }
}
function prr() {
    $args = func_get_args();
    foreach ($args as $arg) {
        pre($arg);
    }
}

/******************************************/

require(__dir__.'/Router/RouteException.php');
require(__dir__.'/Router/Route.php');

$route = new \Router\Route();
$route->removeUriBase('/router');

// degistirelim bu regexp manyakligini
// daha basit ve alisilmis bisi olsun
// /:login|logout => (login|logout) gibi
// '$re~^/user/([0-9]+)~i'; //isterse raw regex pattern de girebilsin ayri bir fn ile (addRE) veya $re ile eklesin (başka {d} falan ekleyemez!!!)

// $route->add('/user', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user.php',
// ]);
// $route->add('/user/{%d}', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user.php',
//     'params' => ['uid']
// ]);
// $route->add('/user/{%d}/{followers|followees}', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user.php',
//     'params' => ['uid', 'tab']
// ]);
// $route->add('/user/{%d}/{followers|followees}', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user-$tab.php',
//     'params' => ['uid', 'tab']
// ]);
// $route->add('/user/{login|logout|register}', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user-$page.php',
//     'params' => ['page']
// ]);
// $route->add('/{403|404}', [
//     '_name_' => 'error',
//     '_file_' => '/routes/errors/$code.php',
//     'params' => ['code']
// ]);

// $route->add('/user/:uid', [
    // '_name_' => 'user',
    // '_file_' => '/routes/user.php',
// ]);
// $route->add('/user/:uid/:tab', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user-$tab.php',
// ]);
// $route->add('/user/:uid/message/:mid', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user-message.php',
// ]);
// $route->add('/user/:uname/message/{%d}', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user-message.php',
//     'params' => ['', 'mid']
// ]);

// Shortcut patterns
$route->addShortcutPattern('digits', '(\d+)');
$route->addShortcutPattern('username', '(?<username>[a-z]{1}[a-z0-9-]{2,10})');

// matches:
// $route->add('/user/$digits/message/$digits', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user-message.php',
//     'params' => ['uid', 'mid']
// ]);

// $route->add('/user/$username', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user.php'
// ]);
// $route->add('/user/$username/{followers|followees}', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user-$tab.php',
//     'params' => ['tab']
// ]);

// Manual regex (with shortcut)
// $route->add('/user/$username/(?<tab>followers|followees)', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user.php',
// ]);

// // Manual regex (idle without param set in source)
// $route->add('/user/(\d+)/(followers|followees)', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user.php',
//     'params' => ['uid', 'tab']
// ]);
// // Manual regex (idle without param set)
// $route->add('/user/(?<uid>\d+)/(?<tab>followers|followees)', [
//     '_name_' => 'user',
//     '_file_' => '/routes/user.php',
// ]);

$route->run();

// pre($route->getPattern());
// pre($route->getPatterns());

// pre('File: '. $route->getFile());
// pre('User Id: '. $route->getParam('uid'));
// pre($route->getParams());

pre('....');
pre($route);
