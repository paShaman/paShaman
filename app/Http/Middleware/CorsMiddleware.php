<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $headers = [
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Methods'     => 'GET,HEAD,PUT,POST,DELETE,PATCH,OPTIONS',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
            'Access-Control-Allow-Headers'     => $request->header('Access-Control-Request-Headers'),
        ];

        //Intercepts OPTIONS requests
        if ($request->isMethod('OPTIONS'))
        {
            return response()->json('{"method":"OPTIONS"}', 200, $headers);
        }

        $response = $next($request);

        if(method_exists($response, 'header')) {
            foreach($headers as $key => $value)
            {
                $response->header($key, $value);
            }
        } else {
            //download
        }

        // Sends it
        return $response;
    }
}
