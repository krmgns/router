<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 Kerem Gunes
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Router;

/**
 * @package Router
 * @object  Router\Route
 * @version 1.1
 * @author  Kerem Gunes <qeremy@gmail>
 */
class Route
{
    /**
     * Request URI/URI Base.
     * @var string
     */
    protected $uri, $uriBase;

    /**
     * Route name.
     * @var string
     */
    protected $name;

    /**
     * Route file.
     * @var string
     */
    protected $file;

    /**
     * Route pattern.
     * @var string
     */
    protected $pattern;

    /**
     * Request params.
     * @var array
     */
    protected $params = [];

    /**
     * Route route.
     * @var array
     */
    protected $route;

    /**
     * Routes routes.
     * @var array
     */
    protected $routes = [];

    /**
     * Route shorcut patters.
     * @var array
     */
    protected $shortcutPatterns = [];

    /**
     * Create new Route object / set self.uri.
     */
    public function __construct() {
        // Set self.uri
        $this->_setUri();
    }

    /**
     * Get hidden properties.
     *
     * @param  string $name
     * @throws Router\RouteException
     * @return mixed|null
     */
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new RouteException('Property does not exists! $name: %s', $name);
    }

    /**
     * Get self properties.
     *
     * It is designed as only property getter.
     *
     * @param  string $name
     * @param  array  $args
     * @throws Router\RouteException
     * @return mixed|null
     */
    public function __call($name, $args = []) {
        if (!method_exists($this, $name)) {
            $cmd = substr($name, 0, 3);
            if ($cmd == 'get') {
                // prepare property name
                $name = lcfirst(substr($name, 3));
                return $this->__get($name);
            }
        }

        throw new RouteException('Method does not exists! $name: %s', $name);
    }

    /**
     * Run object for finding proper route.
     *
     * Loop over added routes, use route pattern and
     * match with Request URI set self.route.
     *
     * @return void
     */
    public function run() {
        foreach ($this->routes as $name => $routes) {
            foreach ($routes as $i => $route) {
                // make a proper regular expression query
                $count =@ (int) preg_match($route['pattern']['re'], $this->uri, $matches);
                if ($count) {
                    // create/set params
                    $params = $this->_paramatize($matches, $route['params']);

                    // set params property
                    $this->params            = $params;

                    // reset route variables
                    $route['_name_']         = $name;
                    $route['_file_']         = $this->_resetFileName($route['_file_'], $params);
                    $route['params']         = $params;

                    // set properties
                    $this->name              = $route['_name_'];
                    $this->file              = $route['_file_'];
                    $this->pattern           = $route['pattern']['re'];
                    $this->route             = $route;

                    // update route
                    $this->routes[$name][$i] = $route;

                    // break first loop
                    break 2;
                }
            }
        }
    }

    /**
     * Add a new route rule.
     *
     * @param  string $route Request path/endpoint.
     * @param  array  $args  Handler of arguments, _name_ / _file_ required.
     * @throws Router\RouteException
     * @return void
     */
    public function add($route, array $args) {
        // check for mandatories
        if (!isset($args['_name_'])) {
            throw new RouteException('Route `_name_` is required!');
        }
        if (!isset($args['_file_'])) {
            throw new RouteException('Route `_file_` is required!');
        }

        // extract variables
        $name   =& $args['_name_'];
        $file   =& $args['_file_'];
        $params =@ (array) $args['params'];
        $extras =@ (array) $args['extras'];

        // init route if not exists
        if (!isset($this->routes[$name])) {
            $this->routes[$name] = [];
        }

        // get routes index
        $index = count($this->routes[$name]);

        // fill routes
        $this->routes[$name][$index] = [
            '_name_'  => $name,
            '_file_'  => $file,
            'params'  => $params,
            'pattern' => $this->_setPattern($route, $params, $name, $index)
        ];
    }

    /**
     * Add a new shortcut pattern that will be used as route pattern.
     *
     * @param  string $name
     * @param  string $value
     * @return void
     */
    public function addShortcutPattern($name, $value) {
        $this->shortcutPatterns[$name] = $value;
    }

    /**
     * Remove base URI.
     *
     * This method useful when needed to remove a part
     * of route URI at the beginning when working with
     * URI's like http://dev.local/router/user.
     *
     * e.g: Remove `/route` $route->removeUriBase('/route')
     *
     * @param  string $base URI base that will be removed
     * @return void
     */
    public function removeUriBase($base) {
        $this->uri = preg_replace(
            '~^'. preg_quote($base) .'~', '', $this->uri);
        $this->uriBase = $base;
    }

    /**
     * Prepare and set route pattern
     *
     * @param string  $route  Target route
     * @param array   $params
     * @param string  $name   Route name
     * @param integer $index  Route index
     */
    protected function _setPattern($route, array $params, $name, $index) {
        // set pattern route as default
        $pattern = $route;

        // make a proper a regular expression query
        // if matches return an integer count
        // else `false` that will be casted as `0`
        $count =@ (int) preg_match_all('~\{(.+?)\}|:(\w+)~', $route, $matches);

        // so, should we go on?
        if ($count) {
            // set replacement keys/vals
            $keys = $vals = [];
            foreach ((array) $matches[0] as $i => $value) {
                // prepare name regex param
                $named = isset($params[$i]) ? '?<'. $params[$i] .'>' : '';

                // named params as with `:` char
                if ($value[0] == ':') {
                    $value  = ltrim($value, ':');
                    $keys[] = sprintf('~:%s~', $value);
                    $vals[] = sprintf('(?<%s>[^/]+)', $value);
                    // add route params
                    $this->routes[$name][$index]['params'][] = $value;
                }
                // normal params
                elseif ($value[0] == '{') {
                    $value  = trim($value, '{}');
                    $length = strlen($value);
                    // type: digit, word, hex
                    if ($value[0] == '%' && $length == 2) {
                        switch ($value[1]) {
                            case 'd':
                                $keys[] = '~{%d}~';
                                $vals[] = sprintf('(%s\d+)', $named);
                                break;
                            case 'w':
                                $keys[] = '~{%w}~';
                                $vals[] = sprintf('(%s\w+)', $named);
                                break;
                            case 'x':
                                $keys[] = '~{%x}~';
                                $vals[] = sprintf('(%s[a-f0-9]+)', $named);
                                break;
                        }
                    }
                    // type: range ({%az} -> ([a-z]+))
                    elseif ($value[0] == '%' && $length > 2) {
                        // extract words
                        list($start, $stop, $operator)
                            =@ (array) [$value[1], $value[2], $value[3]];
                        // set repeat operator
                        $operator = ($operator == '-')
                            ? '' : ($operator == '*') ? '*' : '+';
                        $keys[] = sprintf('~{%s}~', preg_quote($value));
                        $vals[] = sprintf('(%s[%s-%s]%s)', $named, $start, $stop, $operator);
                    }
                    // type: all (eg: {foo|bar} -> (foo|bar))
                    else {
                        $keys[] = sprintf('~{%s}~', preg_quote($value));
                        $vals[] = sprintf('(%s%s)', $named, $value);
                    }
                }
            }

            // make replacement if not empty keys/vals
            if (!empty($keys)) {
                $pattern = preg_replace($keys, $vals, $pattern, 1);
            }
        }

        // replace shortcut patterns
        if (!empty($this->shortcutPatterns)) {
            $count =@ (int) preg_match_all('~(?:\$([\w]+))~', $route, $matches);
            if ($count) {
                foreach ((array) $matches[1] as $key) {
                    if (array_key_exists($key, $this->shortcutPatterns)) {
                        $pattern = str_replace('$'. $key, $this->shortcutPatterns[$key], $pattern);
                    }
                }
            }
        }

        // built regex result
        return [
            'rs' => $route,                // source
            're' => '~^'. $pattern .'$~i', // expression
        ];
    }

    /**
     * Set route params.
     *
     * @param  array $matches Result of self.run() regex matches.
     * @param  array $params  User-provided or extracted params from regex.
     * @return array
     */
    protected function _paramatize(array $matches, array $params) {
        // remove regex input
        array_shift($matches);

        $return = [];
        // combine params & matches
        if (count($matches) == count($params)) {
            $return = array_combine($params, $matches);
        }

        // merge named params and provided params
        $return = (array) ($return + $matches + $params);

        // sort
        ksort($return, SORT_NATURAL);

        return $return;
    }

    /**
     * Reset route file name.
     *
     * Resets file name if matches with params.
     *  route  -> /user/{followers|followees}
     *         -> _file_ = /routes/user-$tab.php
     *         -> params = [tab]
     *  result -> _file_ /routes/user-followers.php
     *
     * @param  string $file   Route file.
     * @param  array  $params Route params.
     * @throws Router\RouteException
     * @return string
     */
    protected function _resetFileName($file, array $params) {
        // make a proper a regular expression query
        $count =@ (int) preg_match_all('~\$(\w+)~', $file, $matches);
        if ($count) {
            foreach ($matches[1] as $i => $value) {
                // get filename
                $replace = $this->getParam($value);
                if (empty($replace)) {
                    throw new RouteException(
                        'Named param index not defined for {%s}, index: %s', $value, $i);
                }
                // replace $filename with matched param
                $file = str_replace('$'. $value, $replace, $file);
            }
        }

        return $file;
    }

    /**
     * Set self.uri.
     *
     * @return string self.uri
     */
    protected function _setUri() {
        // set only once
        if (!isset($this->uri)) {
            $this->uri = urldecode($_SERVER['REQUEST_URI']);
            // remove query string
            if (($qpos = strpos($this->uri, '?')) !== false) {
                $this->uri = substr($this->uri, 0, $qpos);
            }
            $this->uri = trim($this->uri);
        }

        return $this->uri;
    }

    /**
     * Get param.
     *
     * @param  string     $key
     * @param  mixed|null $defval
     * @return mixed|null
     */
    public function getParam($key, $defval = null) {
        return isset($this->params[$key])
            ? $this->params[$key]
            : $defval;
    }

    /**
     * Get route pattern or pattern rs/re.
     *
     * @param  string|null $key Only rs/re valid.
     * @return mixed|null
     */
    public function getPattern($key = null) {
        if ($key != null) {
            return isset($this->route['pattern'][$key])
                ? $this->route['pattern'][$key] : null;
        }

        return isset($this->route['pattern'])
            ? $this->route['pattern'] : null;
    }

    /**
     * Get route patterns.
     *
     * @return array An associative array with rs/re keys.
     */
    public function getPatterns() {
        $patterns = [];
        foreach ($this->routes as $name => $routes) {
            foreach ($routes as $route) {
                $patterns[$name][] = $route['pattern'];
            }
        }

        return $patterns;
    }

    /**
     * Check URI is root or not.
     *
     * @return boolean
     */
    public function isRoot() {
        return $this->uri == '/';
    }

    /**
     * Check route failed or not.
     *
     * @return boolean
     */
    public function isFound() {
        return !empty($this->file);
    }
}
