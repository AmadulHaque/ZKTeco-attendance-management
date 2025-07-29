<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class BlockCrawlerMiddleware
{
    /**
     * List of known bot User-Agents
     *
     * @var array
     */
    protected $botAgents = [
        'bot', 'crawl', 'slurp', 'spider', 'mediapartners',
        'wget', 'curl/', 'python-requests', 'scrapy',
        'httpclient', 'aiohttp', 'java', 'urllib',
        'go-http-client', 'facebookexternalhit', 'headlesschrome',
        'phantomjs', 'selenium', 'puppeteer', 'baiduspider',
        'yandexbot', 'ahrefsbot', 'mj12bot', 'semrushbot',
        'bingbot', 'duckduckbot', 'facebot', 'twitterbot',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Always check even if no User-Agent is present (more secure)

        // check if cache exists
        if (Cache::has('blocked_crawler_' . $request->ip())) {
            return response()->json([
                'error'   => 'Access denied',
                'message' => 'Automated requests are not allowed',
            ], 403);
        }

        if (! $request->hasHeader('User-Agent')) {
            return $this->blockAccess($request, 'No User-Agent header');
        }

        $userAgent = strtolower($request->header('User-Agent'));

        // Empty User-Agent is suspicious
        if (empty($userAgent)) {
            return $this->blockAccess($request, 'Empty User-Agent');
        }

        // Check for bot signatures
        foreach ($this->botAgents as $bot) {
            if (str_contains($userAgent, $bot)) {
                return $this->blockAccess($request, "Matched bot pattern: {$bot}");
            }
        }

        return $next($request);
    }

    /**
     * Block access with a 403 response
     */
    protected function blockAccess(Request $request, string $reason): Response
    {
        Log::warning("Blocked crawler - Reason: {$reason}", [
            'ip'         => $request->ip(),
            'url'        => $request->fullUrl(),
            'user_agent' => $request->header('User-Agent'),
            'referer'    => $request->header('Referer'),
        ]);

        Cache::put('blocked_crawler_' . $request->ip(), true, 3600); // Cache for 1 hour

        return response()->json([
            'error'   => 'Access denied',
            'message' => 'Automated requests are not allowed',
        ], 403);
    }
}