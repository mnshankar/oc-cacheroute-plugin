<?php
namespace SerenityNow\Cacheroute\Classes;

use Illuminate\Http\Request as LaravelRequest;
use SerenityNow\Cacheroute\Models\CacheRoute;
use Closure;

class RouteCacheMiddleware
{
    public function handle(LaravelRequest $request, Closure $next)
    {
        //bail if table does not exist or
        //route not in the list of routes to be cached
        if (!\Schema::hasTable('serenitynow_cacheroute_routes')||
            !($cacheRow = $this->shouldBeCached($request))) {
            return $next($request);
        }
        $cacheKey = $this->getCacheKey($request->url());

        if (\Cache::has($cacheKey)) {
            return \Response::make($this->getCachedContent($request, \Cache::get($cacheKey)), 200);
        }
        $response = $next($request);
        \Cache::put($cacheKey, $response->getContent(), $cacheRow['cache_ttl']);
        return $response;
    }

    //add instrumentation to help with debug. Adding ?debug
    //to a cached url will precede the content with "CACHED"
    protected function getCachedContent($request, $content)
    {
        if ($request->exists('debug')) {
            return '<p class="alert alert-info">CACHED</p>' . $content;
        }
        return $content;
    }

    //generate a cache key based on the url
    protected function getCacheKey($url)
    {
        return 'SerenityNow.Cacheroute.' . str_slug($url);
    }

    protected function shouldBeCached($request)
    {
        $cacheRouteRows = \Cache::remember('SerenityNow.Cacheroute.AllCachedRoutes',
            \Config::get('cms.urlCacheTtl'),
            function () {
                return CacheRoute::orderBy('sort_order')->get()->toArray();
            }
        );
        foreach ($cacheRouteRows as $cacheRow) {
            if ($request->is($cacheRow['route_pattern'])) {
                return $cacheRow;
            }
        }
        return false;
    }
}
