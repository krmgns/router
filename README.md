**What does?**

Implements simple and complex routing operations.

**What doesn't?**

Doesn't implements routes as same as somewhere like general MVC architecture (but could be integrated easily).

<hr size="1" />

**Usage**

- Set your autoloader properly
- Use PHP >= 5.4
- Handle errors with `try/catch` blocks

** Indicators

All provided operators will be setted as a parameter. If it's named, then could be called by its name, otherwise must be called by index.

Let's what operators produce and if you need more, simply use pure RegExp statements...

```text
# shortcuts
{%d}   = Digit -> (\d+)
{%w}   = Words -> (\w+)
{%x}   = Hexes -> ([a-f0-9]+)
{%ac}  = a-c   -> ([a-c]+)
{%az}  = a-z   -> ([a-z]+)
{%az-} = a-z   -> ([a-z])

# named (1)
:uid = All   -> (?<uid>[^/]+)
# named (2)
{uid} = uid  -> (uid)
{followers|followees} = followers|followees  -> (followers|followees)
# named (2) with params
{uid} = uid  -> (?<uid>uid) # params: [uid]
{followers|followees} = followers|followees  -> (?<tab>{followers|followees}) # params: [tab]
```

```php
$route = new \Router\Route();
# This method is useful if you need to remove base URI
# E.g: http://dev.local/router/user
# $route->removeUriBase('/router');
```

Note: After all `add()` calls you need to call `run()` method.

** Simple
```php
# matches: /user
$route->add('/user', [
    '_name_' => 'user',
    '_file_' => '/routes/user.php',
]);

...

# Finally call matcher method after all route rules added
$route->run();

# If don't want to run router for "/" uri
if (!$route->isRoot()) {
    $route->run();
}

# Get route file
$file = $route->getFile();
# Check file (or you can define your file checker / shomething else)
if (is_file($file)) {
    require($file);
}
```

** With params
```php
# You can use param(s) anytime if needed
# $uid = $route->getParam('uid')

# matches: /user/(\d+)
$route->add('/user/{%d}', [
    '_name_' => 'user',
    '_file_' => '/routes/user.php',
    'params' => ['uid']
]);

# matches: /user/(\d+)/(followers|followees)
$route->add('/user/{%d}/{followers|followees}', [
    '_name_' => 'user',
    '_file_' => '/routes/user.php',
    'params' => ['uid', 'tab']
]);

# Or use param(s) as a file rename tool
$route->add('/user/{%d}/{followers|followees}', [
    '_name_' => 'user',
    # At this line, params.tab goes to rename user file
    '_file_' => '/routes/user-$tab.php',
    'params' => ['uid', 'tab']
]);
# Here is the same actions
$route->add('/user/{login|logout|register}', [
    '_name_' => 'user',
    '_file_' => '/routes/user-$page.php',
    'params' => ['page']
]);
$route->add('/{403|404}', [
    '_name_' => 'error',
    '_file_' => '/routes/errors/$code.php',
    'params' => ['code']
]);

```

** Named params
```php
# matches: /user/(?<uid>[^/]+)
$route->add('/user/:uid', [
    '_name_' => 'user',
    '_file_' => '/routes/user.php',
]);

# matches: /user/(?<uid>[^/]+)/(?<tab>[^/]+)
$route->add('/user/:uid/:tab', [
    '_name_' => 'user',
    '_file_' => '/routes/user-$tab.php', # <- rename
]);

# matches: /user/(?<uid>[^/]+)/message/(?<mid>[^/]+)
$route->add('/user/:uid/message/:mid', [
    '_name_' => 'user',
    '_file_' => '/routes/user-message.php',
]);

# matches: /user/(?<uname>[^/]+)/message/(\d+)
$route->add('/user/:uname/message/{%d}', [
    '_name_' => 'user',
    '_file_' => '/routes/user-message.php',
]);
# Or name the digit part too
$route->add('/user/:uname/message/{%d}', [
    '_name_' => 'user',
    '_file_' => '/routes/user-message.php',
    'params' => ['', 'mid']
]);
```

** Shortcut patterns
```php
$route->addShortcutPattern('digits', '(\d+)');
$route->addShortcutPattern('username', '(?<username>[a-z]{1}[a-z0-9-]{2,10})');

# matches: /user/(\d+)/message/(\d+)
$route->add('/user/$digits/message/$digits', [
    '_name_' => 'user',
    '_file_' => '/routes/user-message.php',
    'params' => ['uid', 'mid']
]);

# matches: /user/(?<username>[a-z]{1}[a-z0-9-]{2,10})
$route->add('/user/$username', [
    '_name_' => 'user',
    '_file_' => '/routes/user.php'
]);
# matches: /user/(?<username>[a-z]{1}[a-z0-9-]{2,10})/(?<tab>followers|followees)
$route->add('/user/$username/{followers|followees}', [
    '_name_' => 'user',
    '_file_' => '/routes/user-$tab.php',
    'params' => ['tab']
]);
```

** Manual RegExps (pure for geeks)
```php
$route->add('/user/(\d+)', [
    '_name_' => 'user',
    '_file_' => '/routes/user.php',
]);

# With shortcut
# matches: /user/(?<username>[a-z]{1}[a-z0-9-]{2,10})/(?<tab>followers|followees)
$route->add('/user/$username/(?<tab>followers|followees)', [
    '_name_' => 'user',
    '_file_' => '/routes/user.php',
]);

# Idle, without named params set in source
$route->add('/user/(\d+)/(followers|followees)', [
    '_name_' => 'user',
    '_file_' => '/routes/user.php',
    'params' => ['uid', 'tab']
]);
# Idle, without params set
$route->add('/user/(?<uid>\d+)/(?<tab>followers|followees)', [
    '_name_' => 'user',
    '_file_' => '/routes/user.php',
]);
```

** Using with MVC-like systems (as a sample)
```php
# /mvc/bootstrap.php
$route->add('/user/edit/{%d}', [
    '_name_' => 'user',
    '_file_' => '/mvc/app/contollers/user-controller.php',
    'params' => ['id']
]);

$app = new Application();
$app->setRoute($route);
...
$app->serve();

# /mvc/sys/controller.php
class Controller {
    protected $route;
    ...
    public function __construct(\Router\Route $route) {
        $this->route = $route;
        ...
        if (!$this->route->isRoot()) {
            $routeFile = $this->route->getFile();
            if ($routeFile == null) {
                $this->logger->write('route error, uri: `%s`', $route->uri);
                $body = $this->view->render('/error/404.phtml');
                $this->response->setStatus(404, 'Not Found');
                $this->response->setBody($body);
                return $this->response->send();
            }
        }
        ...
    }
}

# /mvc/app/contollers/user-controller.php
class UserController extends Controller {
    ...
    public function doEdit() {
        $id = (int) $this->route->getParam('id');
        if ($id) {
            $this->model->user->update(...);
        }
    }
}
```

** Using with REST-like systems (as a sample)
```php
# /rest/bootstrap.php
$route->add('/user/edit/{%d}', [
    '_name_' => 'user',
    '_file_' => '/rest/endpoints/user.php',
    'params' => ['id']
]);

$rest = new Rest();
$rest->setRoute($route);
...
$rest->listen();

# /rest/endpoints/user.php
class UserEndpoint extends Rest {
    ...
    public function patch() {
        $user = $this->model->user->find((int) $this->route->getParam($id));
        if ($user) {
            $body = $this->json->encode($user);
            $this->response->setStatus(204);
            $this->response->setBody($body);
            return $this->response->send();
        }
        return $this->response->sendError(404);
    }
}
```
