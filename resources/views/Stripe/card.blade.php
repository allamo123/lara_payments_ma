<div id="ma-stripe-payment">

    <div class="payment-header"></div>

    <form id="ma-stripe-payment-form">

        <div id="ma-stripe-card-element"></div>

        <div id="ma-stripe-card-errors" role="alert"></div>
        
        <button  type="submit" id="ma-stripe-submit-btn">
            Pay
        </button>

        <div id="ma-stripe-card-success" role="alert"></div>

    </form>
@once
<style>
    #ma-stripe-payment {
        width: 100%;
    }
    #ma-stripe-payment-form {
        width: 100%;
    }

    .payment-header {
        text-align: center;
    }

    .payment-header p {
        color: green;
        font-weight: 600;
        font-size: 16px;
        letter-spacing: 1.4px;
    }

    #ma-stripe-card-element {
        width: 100%;
        min-height: 48px;
        padding: 12px 14px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        background: #fff;
        box-sizing: border-box;
    }

    #ma-stripe-card-errors {
        margin-top: 6px;
        font-size: 14px;
        color: #dc3545;
    }

    #ma-stripe-card-success {
        margin-top: 12px;
        font-size: 18px;
        text-align: center;
        color: green
    }

    #ma-stripe-submit-btn {
        width: 100%;
        padding: 12px 16px;
        margin-top: 16px;
        border: 0;
        border-radius: 6px;
        background: #0d6efd;
        color: #fff;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
    }

    #ma-stripe-submit-btn:hover {
        background: #0b5ed7;
    }

    #ma-stripe-submit-btn:disabled {
        opacity: 0.65;
        cursor: not-allowed;
    }
    .loading {
        height: 25px;
        border: 3px solid #dee2e6;
        border-top-color: #0d6dfd27;
        border-radius: 50%;
        width: 25px;
        margin: auto;
        animation: ma-stripe-spin 0.8s linear infinite;
    }


    @keyframes ma-stripe-spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>
@endonce
</div>

<script src="https://js.stripe.com/dahlia/stripe.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.11.0/axios.min.js"></script>

<script src="{{ asset('js/vendor/ma_payment/stripe/MaPaymentStripe.js') }}" type="module"></script>

<script type="module">
import StripePayment from "{{ asset('js/vendor/ma_payment/stripe/MaPaymentStripe.js') }}";

const payment = new StripePayment({
    publishableKey: @json($publishableKey),
    paymentUrl: @json($paymentUrl ?? $retryUrl), // retry url for retry any failed payment 
    successUrl: @json($successUrl),
    amount: @json($amount),
    currency: @json($currency),
    customer: @json($customer ?? []),
    source: @json($source),
});

const card = payment.createCardElement();

card.mount('#ma-stripe-card-element');

const headerElement  = document.querySelector(".payment-header");
const ButtonElement  = document.getElementById('ma-stripe-submit-btn');
const errorElement   = document.getElementById('ma-stripe-card-errors');
const successElement = document.getElementById('ma-stripe-card-success');

document.getElementById('ma-stripe-payment-form').addEventListener('submit', async (event) => {

    event.preventDefault();

    errorElement.textContent = '';
    ButtonElement.setAttribute('disabled', true);
    ButtonElement.innerHTML = '<div class="loading"></div>'

    try {
        
        const { data, success, error } = await payment.pay();

        if (!success) {
            console.log('hi')
            errorElement.textContent = error.message;
            ButtonElement.removeAttribute('disabled', false);
            ButtonElement.innerHTML = 'Pay'
            return;
        }

        if (data.success && data.payment.status === 'succeeded') {
            headerElement.innerHTML = `<p>A payment of ${data.payment.amount_received} ${payment.currency} already has been made.</p>`
            successElement.textContent = 'Payment succeeded';
            ButtonElement.innerHTML = 'Payment Done';
            setTimeout(() => {
                window.location.href= payment.successUrl;
            }, 2500);
        }
        else {
            errorElement.textContent = data.error;
            ButtonElement.removeAttribute('disabled', false);
            ButtonElement.innerHTML = 'Pay'
            console.log(data.error);
            return;
        }
    } catch (error) {
        console.log('Status:', error.response?.status);
        console.log('Data:', error.response?.data);
        console.log('Message:', error.message);
    }
});
</script>