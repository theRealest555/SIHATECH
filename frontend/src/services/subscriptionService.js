import apiClient from '../api/axios';
export const getSubscriptionPlans = (options = {}) => apiClient.get('/api/subscriptions/plans', options);
export const getSubscriptionSetupIntent = () => apiClient.get('/api/subscriptions/setup-intent');
export const subscribeToPlan = data => apiClient.post('/api/subscriptions/subscribe', data);
export const cancelCurrentSubscription = () => apiClient.post('/api/subscriptions/cancel');
export const getUserSubscriptionStatus = (options = {}) => apiClient.get('/api/subscriptions/current', options);
