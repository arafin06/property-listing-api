<?php

namespace Database\Factories;

use App\Enums\PropertyListingType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraphs(3, true),
            'type' => fake()->randomElement(PropertyListingType::cases()),
            'property_type' => fake()->randomElement(PropertyType::cases()),
            'price' => fake()->numberBetween(50_000, 5_000_000),
            'bedrooms' => fake()->numberBetween(1, 6),
            'bathrooms' => fake()->numberBetween(1, 4),
            'area' => fake()->numberBetween(400, 6000),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'zip_code' => fake()->postcode(),
            'status' => PropertyStatus::Active,
        ];
    }

    /**
     * Indicate that the property is for rent.
     */
    public function forRent(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PropertyListingType::Rent,
        ]);
    }

    /**
     * Indicate that the property has been sold.
     */
    public function sold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PropertyStatus::Sold,
        ]);
    }
}
