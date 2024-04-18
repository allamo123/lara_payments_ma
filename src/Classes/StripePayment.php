<?php

namespace Ma\Payments\Classes;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Cache;
use Ma\Payments\Interfaces\PaymentInterface;
use Stripe\StripeClient;

class StripePayment extends BaseController implements PaymentInterface
{
    private $stripe_api_key;
    private $stripe_api_secret;
    private $stripe_currency;

    public function __construct()
    {
        $this->stripe_api_key = config('ma-payments.STRIPE_API_KEY');
        $this->stripe_api_secret = config('ma-payments.STRIPE_API_SECRET');
        $this->stripe_currency = config('ma-payments.STRIPE_CURRENCY');
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

        $required_fields = ['card'];

        $this->checkRequiredFields($required_fields, 'Stripe', func_get_args());

        $stripe = new StripeClient($this->stripe_api_secret);

        if (config('app.env') === 'production') {
            $token = $stripe->tokens->create([
                'card' => [
                    'name' => $this->card['card_holder_name'],
                    'number' =>  $this->card['card_number'],
                    'exp_month' => $this->card['ex_month'],
                    'exp_year' => $this->card['ex_year'],
                    'cvc' => $this->card['cvv']
                ],
            ]);
        }

        $payment = $stripe->charges->create([
            'amount' => $this->amount * 100,
            'currency' =>  $this->stripe_currency,
            'source' => config('app.env') === 'production' ? $token['id'] : 'tok_visa',
            'description' => 'My First Test Charge (created for API docs at https://www.stripe.com/docs/api)',
        ]);

        if ($payment['captured'] === true && $payment['paid'] === true && $payment['status'] === 'succeeded') {

            Cache::forever('payment_id', $payment['id']);

            return [
                'success' => true,
                'transaction' => $payment
            ];
        }
        else {

            return [
                'success' => false
            ];
        }

    }

    public function verify(Request $request)
    {
        $payment_id = Cache::get('payment_id');

        Cache::forget('payment_id');

        if ($payment_id) {

            $stripe = new \Stripe\StripeClient(
                $this->stripe_api_secret
            );

            $transaction = $stripe->charges->retrieve(
                    $payment_id,
                    []
            );

            if ($transaction['captured'] && $transaction['paid'] && $transaction['status'] === 'succeeded') {
                return [
                    'success' => true,
                    'transaction' => $transaction
                ];
            }
        }
        else {
            abort(404);
        }
    }
}
