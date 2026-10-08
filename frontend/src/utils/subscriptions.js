import { loadStripe } from '@stripe/stripe-js';
const clients = new Map();
export function stripeClient(key) {
    if (!key) return null;
    if (!clients.has(key)) clients.set(key, loadStripe(key));
    return clients.get(key);
}
export function money(amount, currency = 'MAD') {
    return new Intl.NumberFormat('en', { style: 'currency', currency }).format(Number(amount));
}
export const cycles = { monthly: 'month', yearly: 'year', 'semi-annual': 'six months' };
