<?php

namespace Tests\Feature\Api;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_the_owner_can_upload_an_image_for_their_property(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/properties/{$property->id}/images", [
            'image' => UploadedFile::fake()->image('house.jpg'),
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $image = $property->images()->first();
        Storage::disk('public')->assertExists($image->path);
        $this->assertFalse($image->is_primary);
    }

    public function test_uploading_a_primary_image_unsets_other_primary_images(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();
        $existing = PropertyImage::factory()->for($property)->primary()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/properties/{$property->id}/images", [
            'image' => UploadedFile::fake()->image('house.jpg'),
            'is_primary' => true,
        ]);

        $response->assertCreated();
        $this->assertFalse($existing->fresh()->is_primary);
        $this->assertTrue($property->images()->latest('id')->first()->is_primary);
    }

    public function test_a_non_owner_cannot_upload_an_image(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $property = Property::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser, 'sanctum')->postJson("/api/properties/{$property->id}/images", [
            'image' => UploadedFile::fake()->image('house.jpg'),
        ]);

        $response->assertStatus(403);
    }

    public function test_image_upload_validates_file_type_and_size(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/properties/{$property->id}/images", [
            'image' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['image']);
    }

    public function test_uploading_an_image_requires_authentication(): void
    {
        $property = Property::factory()->create();

        $response = $this->postJson("/api/properties/{$property->id}/images", [
            'image' => UploadedFile::fake()->image('house.jpg'),
        ]);

        $response->assertStatus(401);
    }

    public function test_the_owner_can_delete_their_property_image(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();
        $image = PropertyImage::factory()->for($property)->create(['path' => 'properties/1/test.jpg']);
        Storage::disk('public')->put($image->path, 'fake-contents');

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/properties/{$property->id}/images/{$image->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('property_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_a_non_owner_cannot_delete_a_property_image(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $property = Property::factory()->for($owner)->create();
        $image = PropertyImage::factory()->for($property)->create();

        $response = $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/properties/{$property->id}/images/{$image->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('property_images', ['id' => $image->id]);
    }

    public function test_an_image_from_another_property_cannot_be_deleted_via_a_mismatched_route(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();
        $otherProperty = Property::factory()->for($user)->create();
        $image = PropertyImage::factory()->for($otherProperty)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/properties/{$property->id}/images/{$image->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('property_images', ['id' => $image->id]);
    }
}
