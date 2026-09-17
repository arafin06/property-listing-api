<?php

namespace App\Http\Controllers\Api;

use App\Enums\PropertyListingType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    use ApiResponses;

    /**
     * Default number of properties returned per page.
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Maximum number of properties that can be requested per page.
     */
    private const MAX_PER_PAGE = 100;

    /**
     * Display a paginated, filterable listing of properties.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'city' => ['sometimes', 'string', 'max:255'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'gte:min_price'],
            'bedrooms' => ['sometimes', 'integer', 'min:0'],
            'type' => ['sometimes', Rule::enum(PropertyListingType::class)],
            'property_type' => ['sometimes', Rule::enum(PropertyType::class)],
            'status' => ['sometimes', Rule::enum(PropertyStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $properties = Property::query()
            ->when($filters['city'] ?? null, fn ($query, $city) => $query->where('city', 'like', "%{$city}%"))
            ->when($filters['min_price'] ?? null, fn ($query, $price) => $query->where('price', '>=', $price))
            ->when($filters['max_price'] ?? null, fn ($query, $price) => $query->where('price', '<=', $price))
            ->when($filters['bedrooms'] ?? null, fn ($query, $bedrooms) => $query->where('bedrooms', '>=', $bedrooms))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['property_type'] ?? null, fn ($query, $propertyType) => $query->where('property_type', $propertyType))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        $paginated = PropertyResource::collection($properties)->response()->getData(true);

        return $this->success('Properties retrieved successfully.', $paginated);
    }

    /**
     * Store a newly created property owned by the authenticated user.
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = $request->user()->properties()->create($request->validated());

        return $this->success('Property created successfully.', new PropertyResource($property->fresh()), 201);
    }

    /**
     * Display the specified property.
     */
    public function show(Property $property): JsonResponse
    {
        return $this->success('Property retrieved successfully.', new PropertyResource($property->load(['user', 'images'])));
    }

    /**
     * Update the specified property. Only the owner may perform this action.
     */
    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $this->authorize('update', $property);

        $property->update($request->validated());

        return $this->success('Property updated successfully.', new PropertyResource($property->fresh()));
    }

    /**
     * Remove the specified property. Only the owner may perform this action.
     */
    public function destroy(Property $property): JsonResponse
    {
        $this->authorize('delete', $property);

        $property->delete();

        return response()->json(null, 204);
    }
}
