<?php

namespace Ma\Payments\Classes;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Ma\Payments\Interfaces\PaymentInterface;
use Stripe\StripeClient;

class StripePayment extends BaseController implements PaymentInterface
{
    private $stripe_api_key;
    private $stripe_api_secret;
    private $stripe_currency;
    private $verify_route;

    public function __construct()
    {
        $this->stripe_api_key = config('ma-payments.STRIPE_API_KEY');
        $this->stripe_api_secret = config('ma-payments.STRIPE_API_SECRET');
        $this->stripe_currency = config('ma-payments.STRIPE_CURRENCY');
        $this->verify_route = config('ma-payments.STRIPE_VERIFY_ROUTE');
    }

    /**
     * @param $amount
     * @param null $user_id
     * @param null $user_first_name
     * @param null $user_last_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $source
     * @return array|Application|RedirectResponse|Redirector
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null, $card = null)
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source, $card);

        $stripe = new StripeClient($this->stripe_api_secret);

        $token = $stripe->tokens->create([
            'card' => [
                'number' =>  $this->card['card_number'],
                'exp_month' => $this->card['ex_month'],
                'exp_year' => $this->card['ex_year'],
                'cvc' => $this->card['cvv']
            ],
        ]);

        $stripe->charges->create([
            'amount' => $this->amount * 100,
            'currency' =>  $this->stripe_currency,
            'source' => $token['id'],
            'description' => 'My First Test Charge (created for API docs at https://www.stripe.com/docs/api)',
        ]);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        #
    }
}
