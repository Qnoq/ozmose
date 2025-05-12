<?php

use App\Models\User;
use function Pest\Laravel\getJson;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('retrieves pricing plans correctly', function () {
    actingAs($this->user);
    
    $response = getJson('/api/subscriptions/plans');
    
    $response->assertOk()
             ->assertJsonStructure([
                 'plans' => [
                     '*' => [
                         'id', 'name', 'price', 'currency', 'interval', 'features'
                     ]
                 ]
             ]);
    
    // Vérifier que les 3 plans sont présents (basic, premium, annual_premium)
    $plans = $response->json('plans');
    expect($plans)->toHaveCount(3);
    
    $planIds = collect($plans)->pluck('id')->toArray();
    expect($planIds)->toContain('basic')
                    ->toContain('premium')
                    ->toContain('annual_premium');
});

it('allows user to subscribe to monthly premium plan', function () {
    actingAs($this->user);
    
    $response = postJson('/api/subscriptions/subscribe', [
        'plan_id' => 'premium',
        'payment_method_id' => 'pm_card_visa' // Simuler un ID de paiement
    ]);
    
    $response->assertOk()
             ->assertJson([
                 'message' => 'Abonnement activé avec succès',
                 'user' => [
                     'is_premium' => true,
                     'subscription_plan' => 'premium'
                 ]
             ]);
    
    $this->user->refresh();
    expect($this->user->is_premium)->toBeTrue();
    expect($this->user->subscription_plan)->toBe('premium');
    expect($this->user->premium_until)->not->toBeNull();
    
    // Vérifier l'enregistrement dans la base de données
    $this->assertDatabaseHas('subscriptions', [
        'user_id' => $this->user->id,
        'plan_id' => 'premium',
        'status' => 'active'
    ]);
});

it('allows user to subscribe to annual premium plan', function () {
    $this->actingAs($this->user);
    
    $response = $this->postJson('/api/subscriptions/subscribe', [
        'plan_id' => 'annual_premium',
        'payment_method_id' => 'pm_card_visa'
    ]);
    
    $response->assertOk();
    
    $this->user->refresh();
    expect($this->user->is_premium)->toBeTrue();
    expect($this->user->subscription_plan)->toBe('annual_premium');
    
    // Vérifier que la date d'expiration est environ un an dans le futur
    $premiumUntil = $this->user->premium_until;
    $oneYearFromNow = now()->addYear();
    
    // Plutôt que de faire une comparaison stricte, vérifions que la différence est minime
    $diffInMinutes = abs($premiumUntil->diffInMinutes($oneYearFromNow));
    expect($diffInMinutes)->toBeLessThanOrEqual(5);
});

it('requires valid plan_id and payment_method_id', function () {
    actingAs($this->user);
    
    $response = postJson('/api/subscriptions/subscribe', [
        'plan_id' => '',
        'payment_method_id' => ''
    ]);
    
    $response->assertStatus(422)
             ->assertJsonValidationErrors(['plan_id', 'payment_method_id']);
});

it('allows user to cancel subscription', function () {
    actingAs($this->user);
    
    // D'abord s'abonner
    postJson('/api/subscriptions/subscribe', [
        'plan_id' => 'premium',
        'payment_method_id' => 'pm_card_visa'
    ]);
    
    // Puis annuler
    $response = postJson('/api/subscriptions/cancel');
    
    $response->assertOk()
             ->assertJson([
                 'message' => 'Abonnement annulé avec succès'
             ]);
    
    // Vérifier que l'abonnement est marqué comme annulé
    $this->assertDatabaseHas('subscriptions', [
        'user_id' => $this->user->id,
        'status' => 'canceled'
    ]);
    
    // L'utilisateur reste premium jusqu'à la date d'expiration
    $this->user->refresh();
    expect($this->user->isPremium())->toBeTrue();
});

it('allows user to resume canceled subscription', function () {
    actingAs($this->user);
    
    // S'abonner puis annuler
    postJson('/api/subscriptions/subscribe', [
        'plan_id' => 'premium',
        'payment_method_id' => 'pm_card_visa'
    ]);
    postJson('/api/subscriptions/cancel');
    
    // Puis reprendre
    $response = postJson('/api/subscriptions/resume');
    
    $response->assertOk()
             ->assertJson([
                 'message' => 'Abonnement repris avec succès.'
             ]);
    
    // Vérifier que l'abonnement est à nouveau actif
    $this->assertDatabaseHas('subscriptions', [
        'user_id' => $this->user->id,
        'status' => 'active'
    ]);
});

it('fails to resume subscription if none was canceled', function () {
    actingAs($this->user);
    
    // Essayer de reprendre sans avoir d'abonnement
    $response = postJson('/api/subscriptions/resume');
    
    $response->assertStatus(404)
             ->assertJson([
                 'message' => 'Aucun abonnement annulé à reprendre.'
             ]);
});

it('retrieves subscription status correctly', function () {
    actingAs($this->user);
    
    // D'abord s'abonner
    postJson('/api/subscriptions/subscribe', [
        'plan_id' => 'premium',
        'payment_method_id' => 'pm_card_visa'
    ]);
    
    $response = getJson('/api/subscriptions/status');
    
    $response->assertOk()
             ->assertJsonStructure([
                 'is_premium',
                 'subscription',
                 'premium_until',
                 'days_remaining',
                 'premium_level'
             ]);
    
    $data = $response->json();
    expect($data['is_premium'])->toBeTrue();
    expect($data['premium_level'])->toBe('premium');
});

it('shows correct status for non-premium user', function () {
    actingAs($this->user);
    
    $response = getJson('/api/subscriptions/status');
    
    $response->assertOk();
    
    $data = $response->json();
    expect($data['is_premium'])->toBeFalse();
    expect($data['premium_level'])->toBe('free');
});

it('extends subscription period when renewing', function () {
    $this->actingAs($this->user);
    
    // S'abonner une première fois
    $this->postJson('/api/subscriptions/subscribe', [
        'plan_id' => 'premium',
        'payment_method_id' => 'pm_card_visa'
    ]);
    
    $this->user->refresh();
    $initialExpiryDate = $this->user->premium_until;
    
    // S'abonner une seconde fois
    $this->postJson('/api/subscriptions/subscribe', [
        'plan_id' => 'premium',
        'payment_method_id' => 'pm_card_visa'
    ]);
    
    $this->user->refresh();
    $newExpiryDate = $this->user->premium_until;
    
    // La nouvelle date d'expiration doit être environ un mois après l'initiale
    $expectedDate = $initialExpiryDate->copy()->addMonth();
    
    expect($newExpiryDate->timestamp)->toBeGreaterThan($expectedDate->subDay()->timestamp);
    // Ajouter 1 seconde de marge pour éviter l'égalité parfaite
    expect($newExpiryDate->timestamp)->toBeLessThanOrEqual($expectedDate->addDay()->timestamp + 1);
});