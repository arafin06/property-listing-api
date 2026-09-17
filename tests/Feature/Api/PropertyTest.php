<?php

namespace Tests\Feature\Api;

use App\Enums\PropertyListingType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_properties_with_pagination(): void
    {
        Property::factory()->count(20)->create();

        $response = $this->getJson('/api/properties');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(15, 'data.data')
            ->assertJsonPath('data.meta.total', 20);
    }

    public function test_properties_can_be_filtered_by_city(): void
    {
        Property::factory()->create(['city' => 'Austin']);
        Property::factory()->create(['city' => 'Dallas']);

        $response = $this->getJson('/api/properties?city=Austin');

        $response->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_properties_can_be_filtered_by_price_range(): void
    {
        Property::factory()->create(['price' => 100_000]);
        Property::factory()->create(['price' => 500_000]);
        Property::factory()->create(['price' => 900_000]);

        $response = $this->getJson('/api/properties?min_price=200000&max_price=600000');

        $response->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_properties_can_be_filtered_by_bedrooms_type_and_property_type(): void
    {
        Property::factory()->create(['bedrooms' => 4, 'type' => PropertyListingType::Rent]);
        Property::factory()->create(['bedrooms' => 1, 'type' => PropertyListingType::Sale]);

        $response = $this->getJson('/api/properties?bedrooms=3&type=rent');

        $response->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_anyone_can_view_a_single_property(): void
    {
        $property = Property::factory()->create();

        $response = $this->getJson("/api/properties/{$property->id}");

        $response->assertOk()->assertJsonPath('data.id', $property->id);
    }

    public function test_an_authenticated_user_can_create_a_property(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/properties', [
            'title' => 'Cozy Family Home',
            'description' => 'A lovely place to live.',
            'type' => 'sale',
            'property_type' => 'residential',
            'price' => 250000,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'area' => 1800,
            'address' => '123 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '78701',
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('properties', ['title' => 'Cozy Family Home', 'user_id' => $user->id]);
    }

    public function test_creating_a_property_requires_authentication(): void
    {
        $response = $this->postJson('/api/properties', []);

        $response->assertStatus(401);
    }

    public function test_creating_a_property_validates_input(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/properties', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['title', 'price', 'city']);
    }

    public function test_the_owner_can_update_their_property(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/properties/{$property->id}", ['title' => 'Updated Title']);

        $response->assertOk()->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_a_non_owner_cannot_update_a_property(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $property = Property::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser, 'sanctum')
            ->putJson("/api/properties/{$property->id}", ['title' => 'Hacked Title']);

        $response->assertStatus(403);
    }

    public function test_the_owner_can_delete_their_property(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/properties/{$property->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
    }

    public function test_a_non_owner_cannot_delete_a_property(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $property = Property::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser, 'sanctum')->deleteJson("/api/properties/{$property->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('properties', ['id' => $property->id]);
    }
}
