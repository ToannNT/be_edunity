<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class LanguageMiddleware
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
        // Kiểm tra nếu header 'lang' có tồn tại
        if ($request->hasHeader('lang')) {
            $language = $request->header('lang');

            // Kiểm tra ngôn ngữ có hợp lệ không (vi hoặc en)
            if (in_array($language, ['vi', 'en'])) {
                App::setLocale($language);  // Đặt ngôn ngữ
            }
        }else{
            App::setLocale('vi');
        }

        return $next($request);
    }
}
