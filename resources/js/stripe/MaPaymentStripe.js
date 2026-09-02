export default class MaPaymentStripe
{
    constructor({
        publishableKey,
        paymentUrl,
        successUrl,
        amount,
        currency,
        customer,
        source = 'card',
    })
    {
        this.publishableKey = publishableKey;
        this.paymentUrl = paymentUrl;
        this.successUrl = successUrl;
        this.amount = amount;
        this.currency = currency;
        this.customer = customer;
        this.source = source;

        this.stripe = Stripe(this.publishableKey);
        this.elements = this.stripe.elements();
        this.card = null;
    }

    createCardElement(options = {}){
        this.card = this.elements.create('card', {
            hidePostalCode: true,
            disableLink: true,
            style: {
                base: {
                    padding: '15px',
                    fontSize: '16px',
                    fontFamily: '"Inter", sans-serif',
                    lineHeight: '30px',
                    color: '#212529',
                    '::placeholder': {
                        color: '#9ca3af',
                    },
                },
                invalid: {
                    color: '#dc3545',
                },
            },
            ...options
        });

        return this.card;
    }

    async createPaymentMethod() {
        if (!this.card) {
            throw new Error('Card element has not been created.');
        }

        return await this.stripe.createPaymentMethod({
            type: 'card',
            card: this.card,
            billing_details: {
                email: this.customer?.email,
                phone: this.customer?.phone,
                name: this.customer?.name,
            },
        });
    }

    async pay() {
        const { paymentMethod, error } = await this.createPaymentMethod();

        if(error)
        {
            return {
                success: false,
                error
            }
        }

        const { data } = await axios.post(this.paymentUrl, {
            amount: this.amount,
            currency: this.currency,
            customer: this.customer,
            source: this.source,
            payment_method: paymentMethod,
        });

         return {
            success: true,
            data,
        };
    }
}