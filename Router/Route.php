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
 * @object  Route
 * @version 1.0
 * @author  Kerem Gunes <qeremy@gmail>
 */
class Route
{
    /**
     * Request URI/URI Base
     * @var str
     */
    protected $uri, $uriBase;

    /**
     * Route name
     * @var str
     */
    protected $name;

    /**
     * Route file
     * @var str
     */
    protected $file;

    /**
     * Route pattern
     * @var str
     */
    protected $pattern;

    /**
     * Request params
     * @var array
     */
    protected $params = [];

    /**
     * Route route
     * @var array
     */
    protected $route;

    /**
     * Routes routes
     * @var array
     */
    protected $routes = [];

    /**
     * Route shorcut patters
     * @var array
     */
    protected $shortcutPatterns = [];



    /**
     * Create new Route object and set self.uri
     */
    public function __construct() {
        // Set self.uri
        $this->_setUri();
    }

    /**
     * Get hidden properties
     *
     * @param  str      $name
     * @return mix|null
     * @throws RouteException If property does not exists
     */
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new RouteException(
            'Property does not exists! $name: %s', $name);
    }

    /**
     * Get self properties
     *
     * It is designed as only property getter
     *
     * @param  str      $name
     * @param  array    $args
     * @return mix|null
     * @throws RouteException If method does not exists
     */
    public function __call($name, $args = []) {
        if (!method_exists($this, $name)) {
            $cmd = substr($name, 0, 3);
            if ($cmd == 'get') {
                // Prepare property name
                $name = lcfirst(substr($name, 3));
                return $this->__get($name);
            }
        }
        throw new RouteException(
            'Method does not exists! $name: %s', $name);
    }

    /**
     * Run object for finding proper route
     *
     * Loop over added routes, use route pattern
     * and match with Request URI set self.route
     */
    public function run() {
        foreach ($this->routes as $name => $routes) {
            foreach ($routes as $i => $route) {
                // Make a proper Regular Expression query
                $count =@ (int) preg_match($route['pattern']['re'], $this->uri, $matches);
                if ($count) {
                    // Create/set params
                    $params = $this->_paramatize($matches, $route['params']);

                    // Set params property
                    $this->params            = $params;

                    // Reset route variables
                    $route['_name_']         = $name;
                    $route['_file_']         = $this->_resetFileName($route['_file_'], $params);
                    $route['params']         = $params;

                    // Set properties
                    $this->name              = $route['_name_'];
                    $this->file              = $route['_file_'];
                    $this->pattern           = $route['pattern']['re'];
                    $this->route             = $route;

                    // Update route
                    $this->routes[$name][$i] = $route;

                    // Break first loop
                    break 2;
                }
            }
        }
    }

    /**
     * Add a new route rule
     *
     * @param  str   $route Request path/endpoint
     * @param  array $args  Handler of arguments (_name_ and _file_ required)
     */
    public function add($route, array $args) {
        // Check for mandatories
        if (!isset($args['_name_'])) {
            throw new RouteException('Route `_name_` is not set!');
        }
        if (!isset($args['_file_'])) {
            throw new RouteException('Route `_file_` is not set!');
        }

        // Extract variables
        $name   =& $args['_name_'];
        $file   =& $args['_file_'];
        $params =@ (array) $args['params'];
        $extras =@ (array) $args['extras'];

        // Init route if not exists
        if (!isset($this->routes[$name])) {
            $this->routes[$name] = [];
        }

        // Get routes index
        $index = count($this->routes[$name]);

        // Fill routes
        $this->routes[$name][$index] = [
            '_name_'  => $name,
            '_file_'  => $file,
            'params'  => $params,
            'pattern' => $this->_setPattern($route, $params, $name, $index)
        ];
    }

    /**
     * Add a new shortcut pattern that will be used
     * as route pattern
     *
     * @param str $name
     * @param str $value
     */
    public function addShortcutPattern($name, $value) {
        $this->shortcutPatterns[$name] = $value;
    }

    /**
     * Remove base URI
     *
     * This method useful when needed to remove
     * a part of route URI at the beginning when working
     * with URI's like http://dev.local/router/user
     * E.g: Remove `/route` $route->removeUriBase('/route')
     *
     * @param str $base URI base that will be removed
     */
    public function removeUriBase($base) {
        $this->uri = preg_replace(
            '~^'. preg_quote($base) .'~', '', $this->uri);
        $this->uriBase = $base;
    }

    /**
     * Prepare and set route pattern
     *
     * @param str   $route  Target route
     * @param array $params
     * @param str   $name   Route name
     * @param int   $index  Route index
     */
    protected function _setPattern($route, array $params, $name, $index) {
        // Set pattern route as default
        $pattern = $route;

        // Make a proper a Regular Expression query
        // If matches return an integer count
        // Else `false` that will be casted as `0`
        $count =@ (int) preg_match_all('~\{(.+?)\}|:(\w+)~', $route, $matches);

        // So, should we go on?
        if ($count) {
            // Set replacement keys/vals
            $keys = $vals = [];
            foreach ((array) $matches[0] as $i => $value) {
                // Prepare name regex param
                $named = isset($params[$i]) ? '?<'. $params[$i] .'>' : '';

                // Named params as with `:` char
                if ($value[0] == ':') {
                    $value  = ltrim($value, ':');
                    $keys[] = sprintf('~:%s~', $value);
                    $vals[] = sprintf('(?<%s>[^/]+)', $value);
                    // Add route params
                    $this->routes[$name][$index]['params'][] = $value;
                }
                // Normal params
                elseif ($value[0] == '{') {
                    $value  = trim($value, '{}');
                    $length = strlen($value);
                    // Type: digit, word, hex
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
                    // Type: range ({%az} -> ([a-z]+))
                    elseif ($value[0] == '%' && $length > 2) {
                        // Extract words
                        list($start, $stop, $operator)
                            =@ (array) [$value[1], $value[2], $value[3]];
                        // Set repeat operator
                        $operator = ($operator == '-')
                            ? '' : ($operator == '*') ? '*' : '+';
                        $keys[] = sprintf('~{%s}~', preg_quote($value));
                        $vals[] = sprintf('(%s[%s-%s]%s)', $named, $start, $stop, $operator);
                    }
                    // Type: all (eg: {foo|bar} -> (foo|bar))
                    else {
                        $keys[] = sprintf('~{%s}~', preg_quote($value));
                        $vals[] = sprintf('(%s%s)', $named, $value);
                    }
                }
            }

            // Make replacement if not empty keys/vals
            if (!empty($keys)) {
                $pattern = preg_replace($keys, $vals, $pattern, 1);
            }
        }

        // Replace shortcut patterns
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

        // Built regex result
        return [
            'rs' => $route,                // source
            're' => '~^'. $pattern .'$~i', // expression
        ];
    }

    /**
     * Set route params
     *
     * @param  array $matches Result of self.run() regex matches
     * @param  array $params  User-provided or extracted params from regex
     * @return array
     */
    protected function _paramatize(array $matches, array $params) {
        // Remove regex input
        array_shift($matches);

        $return = [];
        // Combine params & matches
        if (count($matches) == count($params)) {
            $return = array_combine($params, $matches);
        }

        // Merge named params and provided params
        $return = (array) ($return + $matches + $params);

        // Sort
        ksort($return, SORT_NATURAL);

        return $return;
    }

    /**
     * Reset route file name
     *
     * Resets file name if matches with params, e.g:
     *  route  -> /user/{followers|followees}
     *  args   -> _file_ = /routes/user-$tab.php
     *  args   -> params = [tab]
     *  result -> _file_ /routes/user-followers.php
     *
     * @param  str   $file    Route file
     * @param  array $params  Route params
     * @return str   $file    Route file
     * @throws RouteException If named param index not defined
     */
    protected function _resetFileName($file, array $params) {
        // Make a proper a Regular Expression query
        $count =@ (int) preg_match_all('~\$(\w+)~', $file, $matches);
        if ($count) {
            foreach ($matches[1] as $i => $value) {
                // Get filename
                $replace = $this->getParam($value);
                if (empty($replace)) {
                    throw new RouteException(
                        'Named param index not defined for {%s}, index: %s', $value, $i);
                }
                // Replace $filename with matched param
                $file = str_replace('$'. $value, $replace, $file);
            }
        }

        return $file;
    }

    /**
     * Set self.uri
     *
     * @return str self.uri
     */
    protected function _setUri() {
        // Set only once
        if (!isset($this->uri)) {
            $this->uri = urldecode($_SERVER['REQUEST_URI']);
            // Remove query string
            if (($qpos = strpos($this->uri, '?')) !== false) {
                $this->uri = substr($this->uri, 0, $qpos);
            }
            $this->uri = trim($this->uri);
        }

        return $this->uri;
    }

    /**
     * Get param
     *
     * @param  str      $key
     * @param  mix|null $defval
     * @return mix|null
     */
    public function getParam($key, $defval = null) {
        return isset($this->params[$key])
            ? $this->params[$key]
            : $defval;
    }

    /**
     * Get route pattern or pattern rs/re
     *
     * @param  str|null $key Only rs/re valid
     * @return mix|null
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
     * Get route patterns
     *
     * @return array An associative array with rs/re keys
     */
    public function getPatterns() {
        $params = [];
        foreach ($this->routes as $name => $routes) {
            foreach ($routes as $route) {
                $params[$name][] = $route['pattern'];
            }
        }

        return $params;
    }

    /**
     * Check URI is root or not
     *
     * @return bool
     */
    public function isRoot() {
        return $this->uri == '/';
    }

    /**
     * Check route failed or not
     *
     * @return bool
     */
    public function isFound() {
        return !empty($this->file);
    }
}
