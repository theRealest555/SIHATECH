<?php

namespace Database\Seeders;

use App\Models\Abonnement;
use Illuminate\Database\Seeder;

class SubscriptionPlansSeeder extends Seeder
{
    public function run()
    {
        $plans = [
            [
                'name' => 'Basic',
                'description' => 'Plan de base pour débuter',
                'price' => 199.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'Gestion jusqu\'à 50 rendez-vous par mois',
                    'Profil de base',
                    'Support par email',
                    'Statistiques basiques',
                ],
                'is_active' => true,
                'stripe_price_id' => env('STRIPE_PRICE_BASIC_MONTHLY', 'price_basic_monthly'),
            ],
            [
                'name' => 'Premium',
                'description' => 'Plan professionnel avec toutes les fonctionnalités',
                'price' => 499.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'Rendez-vous illimités',
                    'Profil premium avec mise en avant',
                    'Support prioritaire 24/7',
                    'Statistiques avancées',
                    'Rappels SMS aux patients',
                    'Gestion multi-cabinet',
                    'Export des données',
                ],
                'is_active' => true,
                'stripe_price_id' => env('STRIPE_PRICE_PREMIUM_MONTHLY', 'price_premium_monthly'),
            ],
            [
                'name' => 'Premium Annuel',
                'description' => 'Plan Premium avec 2 mois offerts',
                'price' => 4990.00,
                'billing_cycle' => 'yearly',
                'features' => [
                    'Toutes les fonctionnalités Premium',
                    '2 mois offerts (économisez 998 MAD)',
                    'Support VIP',
                    'Formation personnalisée',
                ],
                'is_active' => true,
                'stripe_price_id' => env('STRIPE_PRICE_PREMIUM_YEARLY', 'price_premium_yearly'),
            ],
        ];

        foreach ($plans as $plan) {
            Abonnement::updateOrCreate(
                ['name' => $plan['name'], 'billing_cycle' => $plan['billing_cycle']],
                $plan
            );
        }
    }
}
