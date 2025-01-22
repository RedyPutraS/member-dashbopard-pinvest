<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if(!defined('CATEGORY_FAQ')){
            $faq = [
                'faq'=>'FAQ',
                'about-us'=>'About us',
                'copyright'=>'Copyright',
                'collabs-with-us'=>'Collabs With Us',
                'membership'=>'Membership',
                'cyber'=>'Cyber'
            ];
            define('CATEGORY_FAQ', $faq);
        }
        if(!defined('APP_TYPE')){
            $app_type = ['online-course','event','article','youtube','spotify'];
            define('APP_TYPE', $app_type);
        }
    }
}
