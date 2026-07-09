<?php

namespace App\Http\Middleware;

use Closure;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Throwable;

class Security
{
    public function handle($request, Closure $next)
    {  
        $securityDataSession = Session::get('securityData');
        
        if($securityDataSession && ($securityDataSession['status'] == 'not_verified' || $securityDataSession['status'] == 'multiple_domain')){
            if ($request->is('admin')) {
                return $next($request); 
            }
            return redirect()->route('back.dashboard');
        }  
            
        if ($request->is('admin')) {
            
            $route = Route::getRoutes()->match($request);
              
            if($route && $route->getName()){

                $domain = request()->getHost();
                
                try {
                    $client = new Client();
                    $response = $client->post('https://support.geniusdevs.com/api/clients/verify', [
                        'connect_timeout' => 3,
                        'timeout' => 5,
                        'http_errors' => false,
                        'form_params' => [
                            'domin_url' => $domain,
                        ],
                    ]);

                    $responseBody = json_decode((string) $response->getBody(), true);

                    if ($response->getStatusCode() >= 200
                        && $response->getStatusCode() < 300
                        && is_array($responseBody)
                        && isset($responseBody['status'])) {
                        Session::put('securityData', $responseBody);
                    } else {
                        Log::warning('License verification service returned an invalid response.', [
                            'domain' => $domain,
                            'status_code' => $response->getStatusCode(),
                        ]);
                    }
                } catch (Throwable $exception) {
                    Log::warning('License verification service is unavailable.', [
                        'domain' => $domain,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
            
            $securityDataSession2 = Session::get('securityData');

            if($securityDataSession2 && ($securityDataSession2['status'] == 'not_verified' || $securityDataSession2['status'] == 'multiple_domain')){
                if ($request->is('admin')) {
                    return $next($request); 
                }
                return redirect()->route('back.dashboard');
            }
        }
        
        return $next($request); 
    }
}
