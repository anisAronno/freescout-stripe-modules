<?php

namespace Modules\Stripe\Providers;

use App\Conversation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ServiceProvider;
use Modules\Stripe\Api\Stripe;
use Modules\Stripe\Entities\StripeSetting;
use View;
use Illuminate\Support\Facades\Cache;

//Module alias
define('STRIPE_MODULE', 'stripe');

class StripeServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
    * Register the service provider.
    *
    * @return void
    */
    public function register()
    {
        $this->registerConfig();
        $this->registerTranslations();
    }

    /**
     * Boot the application events.
     *
     * @return void
     */

    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();
    }


     /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/stripe.php' => config_path('stripe.php'),
        ], 'config');


        $this->mergeConfigFrom(
            __DIR__.'/../Config/stripe.php',
            'stripe'
        );
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        \Eventy::addFilter('stylesheets', function ($styles) {
            $styles[] = \Module::getPublicPath(STRIPE_MODULE).'/css/stripe.css';
            return $styles;
        });

        \Eventy::addAction('customer.profile.extra', [$this, 'customerProfileExtra']);

        \Eventy::addAction('mailboxes.settings.menu', [$this, 'mailboxSettingsMenu']);

        \Eventy::addFilter('javascripts', function ($javascripts) {
            $javascripts[] = \Module::getPublicPath(STRIPE_MODULE).'/js/stripe.js';
            return $javascripts;
        });
    }

    /**
     * Show data in customer Profile Extra section
     * @param mixed $customer
     * @return void
     */
    public function customerProfileExtra($customer)
    {
        $productWithInvoices = $this->getStripeInvoices($customer->getMainEmail());
        $productWithSubscriptions = $this->getStripeSubscriptions($customer->getMainEmail());

        echo View::make('stripe::customer_fields_view', [
            'productWithInvoices' => $productWithInvoices,
            'productWithSubscriptions' => $productWithSubscriptions
        ])->render();
    }

    public function getStripeSecretKey($email)
    {
        $customer = Conversation::where('customer_email', $email)->first();

        $stripeSettings = StripeSetting::select('stripe_secret_key')->where('mailbox_id', $customer->mailbox->id)->first();

        if (isset($stripeSettings)) {
            return Crypt::decryptString($stripeSettings->stripe_secret_key);
        } else {
            return '';
        }
    }

    /**
     * Get Stripe Subscription
     * @param mixed $email
     * @return mixed
     */
    public function getStripeInvoices($email)
    {
        $stripeSecret = $this->getStripeSecretKey($email);
        if (! empty($stripeSecret)) {
            $stripe = new Stripe($stripeSecret);
            $stripeInvoices = $stripe->getInvoices($email);

            return $stripeInvoices;
        }

        return false;
    }
    /**
     * Get Stripe Invoice
     * @param mixed $email
     * @return mixed
     */
    public function getStripeSubscriptions($email)
    {
        $stripeSecret = $this->getStripeSecretKey($email);
        if (! empty($stripeSecret)) {
            $stripe = new Stripe($stripeSecret);
            $stripeSubscriptions = $stripe->getSubscriptions($email);

            return $stripeSubscriptions;
        }

        return false;
    }

     /**
     * Show data in customer Profile Extra section
     * @param mixed $customer
     * @return void
     */
    public function mailboxSettingsMenu($mailbox)
    {
        echo View::make('stripe::mailbox_settings_menu', [
            'mailbox' => $mailbox,
        ])->render();
    }

    /**
     * Register views.
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/stripe');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/stripe';
        }, \Config::get('view.paths')), [$sourcePath]), 'stripe');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
