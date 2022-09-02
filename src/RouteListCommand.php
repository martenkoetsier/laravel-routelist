<?php

namespace Martenkoetsier\LaravelRoutelist;

use App\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Foundation\Console\RouteListCommand as Command;
use Illuminate\Routing\ViewController;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Extended version from the framework, allowing for an alternative output.
 * 
 * - more emphasis on route names
 * - shortened controller names
 * - middleware indicated by alias if possible and placed on as few lines as possible
 * - small other tweaks
 * 
 * This class extends the original Laravel RouteListCommand class by overwriting some functions.
 */
class RouteListCommand extends Command {
    /**
     * Convert the given routes to regular CLI output.
     *
     * @param \Illuminate\Support\Collection $routes
     * @return array
     */
    protected function forCli($routes) {
        $routes = $routes->map(
            fn ($route) => array_merge($route, [
                'method' => $route['method'] == 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS' ? 'ANY' : $route['method'],
                'uri' => $route['domain'] ? ($route['domain'] . '/' . ltrim($route['uri'], '/')) : $route['uri'],
                'name' => $route['name'],
                'action' => $this->formatActionForCli($route),
            ]),
        );

        // determine the maximum width of the uri and method for alignment
        $maxUri = max(array_map('mb_strlen', $routes->pluck('uri')->all()));
        $maxMethod = max(array_map('mb_strlen', $routes->pluck('method')->all()));
        $maxName = max(array_map('mb_strlen', $routes->pluck('name')->all()));

        $terminalWidth = $this->getTerminalWidth();

        $routeCount = $this->determineRouteCountOutput($routes, $terminalWidth);

        $knownMiddleware = resolve(HttpKernel::class)->getRouteMiddleware();

        return $routes->map(function ($route) use ($maxUri, $maxMethod, $maxName, $terminalWidth, $knownMiddleware) {
            [
                'action' => $action,
                'name' => $name,
                'domain' => $domain,
                'method' => $method,
                'middleware' => $middleware,
                'uri' => $uri,
            ] = $route;

            if ($middleware) {
                // replace middleware class names into aliases from Kernel
                if (!$this->output->isVerbose()) {
                    $middleware = str_replace(
                        array_values($knownMiddleware),
                        array_keys($knownMiddleware),
                        $middleware
                    );
                }
            }


            // spacer right after the method, to align the uri
            $spacer1 = str_repeat(' ', max($maxMethod - mb_strlen($method), 0));

            // spacer between the uri and the route name
            $spacer2 = str_repeat(' ', max($maxUri - mb_strlen($uri), 0));

            // spacer between the route name and middleware (if applicable)
            $spacer3 = str_repeat(' ', max($maxName - mb_strlen($name), 0));
            $mw = str_replace("\n", "  ", $middleware);
            $mwsep = ' ';
            if ($this->output->isVerbose()) {
                $spacer3 = '';
                $mw = '';
                $mwsep = '';
            }

            // spacer between the route name and action (action is right aligned)
            $spacer4 = str_repeat(' ', max(
                $terminalWidth - mb_strlen("$method $spacer1 $uri $spacer2 $name$mwsep$spacer3$mwsep$mw $action") - ($action ? 1 : 0),
                0
            ));

            // if the output is too long, try to shorten it. Start with reducing spacer3 length (move the middleware to
            // the left). If that is not enough, reduce spacer2 length (move the route name and middleware to the left).
            // If that is still not enough and we are not in non-verbose mode, shorten the middleware. If that is still
            // not enough and we are not in non-verbose mode, shorten the action. In verbose mode, the line just spills
            // over to the next console line.
            if (
                $action
                && mb_strlen("$method $spacer1 $uri $spacer2 $name$mwsep$spacer3$mwsep$mw $spacer4 $action") > $terminalWidth
            ) {
                if (strlen($spacer4) === 0) {
                    while (
                        strlen($spacer3) > 0
                        && mb_strlen("$method $spacer1 $uri $spacer2 $name$mwsep$spacer3$mwsep$mw $spacer4 $action") > $terminalWidth
                    ) {
                        $spacer3 = substr($spacer3, 1);
                    }
                    while (
                        strlen($spacer2) > 0
                        && mb_strlen("$method $spacer1 $uri $spacer2 $name$mwsep$spacer3$mwsep$mw $spacer4 $action") > $terminalWidth
                    ) {
                        $spacer2 = substr($spacer2, 1);
                    }
                }
                if (!$this->output->isVerbose()) {
                    while (mb_strlen($mw) > 1
                        && mb_strlen("$method $spacer1 $uri $spacer2 $name$mwsep$spacer3$mwsep$mw $spacer4 $action") > $terminalWidth
                    ) {
                        $mw = mb_substr($mw, 0, -2) . '…';
                    }
                    if (mb_strlen("$method $spacer1 $uri $spacer2 $name$mwsep$spacer3$mwsep$mw $spacer4 $action") > $terminalWidth) {
                        $action = '…' . mb_substr(
                            $action,
                            - ($terminalWidth - 1 - mb_strlen("$method $spacer1 $uri $spacer2 $name$mwsep$spacer3$mwsep$mw $spacer4 "))
                        );
                    }
                }
            }

            // format the various output fields: coloring and padding where applicable in the actions, the fixed string
            // 'Controller' is replaced by a colored, encircled c (©) (see function formatActionForCli) and the @-sign
            // is replaced by a double colon (one glyph) for shorter, more clear output.
            $method = '<fg=white;options=bold>' . Str::of($method)->explode('|')->map(
                fn ($method) => sprintf('<fg=%s>%s</>', $this->verbColors[$method] ?? 'default', $method),
            )->implode('<fg=#6C7280>|</>') . '</>';
            $spacer1 = " $spacer1 ";
            $uri = '<fg=white>' . preg_replace('#({[^}]+})#', '<fg=yellow>$1</>', $uri) . '</>';
            $spacer2 = " $spacer2 ";
            $name = $name ? "<fg=green>$name</>" : "";
            if (!$this->output->isVerbose()) {
                $spacer3 = " $spacer3 ";
            }
            $spacer4 = " $spacer4 ";
            $action = $action ? "<fg=blue>" . str_replace(['©', '@'], ['<fg=white>©</>', '∷'], $action) . "</>" : '';

            // combine fields into a single, formatted line of output
            $output = '<fg=#6C7280>' . $method . $spacer1 . $uri . $spacer2 . $name . $spacer3 . $mw . $spacer4 . $action . '</>';

            // detect the larger spans of white space and replace those by space-dot-space' sequences, aligning the dots
            // to fixed stops
            // for this, we need to know the string length of the _visible_ output, not the formatting codes.
            $unformatted = mb_str_split(preg_replace('/<.*?>/', '', $output), 3);
            $unformatted = implode("", array_map(function ($chunk) {
                return $chunk === '   ' ? ' . ' : $chunk;
            }, $unformatted));
            // knowing the exact length(s) of the spans of white-space, replace those in the real output
            if (preg_match_all('/([ .]{3,})/', $unformatted, $matches, PREG_PATTERN_ORDER)) {
                foreach ($matches[1] as $spacer) {
                    $output = preg_replace('/([^ ]) {' . strlen($spacer) . '}([^ ])/', "\$1$spacer\$2", $output);
                }
            }
            // and place the line into an array
            $output = [$output];

            // add middleware info if applicable
            // Middleware is listed by their aliases as defined in the App\Http\Kernel where possible or by class name
            // otherwise. In very verbose mode, middleware is always listed by full class name.
            if ($this->output->isVerbose() && $middleware) {
                // replace middleware class names into aliases from Kernel
                if (!$this->output->isVeryVerbose()) {
                    $middleware = str_replace(
                        array_values($knownMiddleware),
                        array_keys($knownMiddleware),
                        $middleware
                    );
                }
                // place middlewares, each separated by two spaces, on as few lines as possible and add the line(s) to
                // the output
                $line = '';
                foreach (explode("\n", $middleware) as $part) {
                    if (!$line) {
                        $line = str_repeat(' ', $maxMethod + 2) . $part;
                    } else {
                        if (mb_strlen("$line  $part") > $terminalWidth) {
                            $output[] = "<fg=#6C7280>$line</>";
                            $line = str_repeat(' ', $maxMethod + 2) . $part;
                        } else {
                            $line .= "  $part";
                        }
                    }
                }
                $output[] = "<fg=#6C7280>$line</>";
            }
            return $output;
        })
            ->flatten()
            ->filter()
            ->prepend('')
            ->push('')->push($routeCount)->push('')
            ->toArray();
    }

    /**
     * Get the formatted action for display on the CLI.
     *
     * @param array $route
     * @return string
     */
    protected function formatActionForCli($route) {
        $action = $route['action'];

        // detect some special cases: closures and view-actions
        if ($action === 'Closure') {
            return 'closure';
        }
        if ($action === ViewController::class) {
            return 'view';
        }

        // do not include the standard root controller namespace to the output
        $rootControllerNamespace = $this->laravel[UrlGenerator::class]->getRootControllerNamespace()
            ?? ($this->laravel->getNamespace() . 'Http\\Controllers');

        if (str_starts_with($action, $rootControllerNamespace)) {
            return str_replace('Controller', '©', substr($action, mb_strlen($rootControllerNamespace) + 1));
        }

        $actionClass = explode('@', $action)[0];

        // add package / vendor names to some actions
        if (
            class_exists($actionClass)
            && str_starts_with((new ReflectionClass($actionClass))->getFilename(), base_path('vendor'))
        ) {
            $actionCollection = collect(explode('\\', $action));

            return $actionCollection->take(2)->implode('\\') . ': ' . str_replace('Controller', '©', "{$actionCollection->last()}");
        }

        return str_replace('Controller', '©', $action);
    }

    /**
     * Determine and return the output for displaying the number of routes in the CLI output.
     *
     * @param \Illuminate\Support\Collection $routes
     * @param int $terminalWidth
     * @return string
     */
    protected function determineRouteCountOutput($routes, $terminalWidth) {
        $routeCountText = 'Showing [' . $routes->count() . '] routes';

        $offset = $terminalWidth - mb_strlen($routeCountText);

        $spaces = str_repeat(' ', $offset);

        return $spaces . '<fg=blue;options=bold>' . $routeCountText . '</>';
    }
}
