<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImageRequest;
use App\Http\Resources\PropertyImageResource;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;

class PropertyImageController extends Controller
{
    use ApiResponses;

    /**
     * Upload a new image for the given property. Only the owner may perform this action.
     */
    public function store(StoreImageRequest $request, Property $property, ImageUploadService $imageUploadService): JsonResponse
    {
        $isPrimary = $request->boolean('is_primary');

        if ($isPrimary) {
            $property->images()->update(['is_primary' => false]);
        }

        $path = $imageUploadService->handle($request->file('image'), $property->id);

        $image = $property->images()->create([
            'path' => $path,
            'is_primary' => $isPrimary,
        ]);

        return $this->success('Image uploaded successfully.', new PropertyImageResource($image), 201);
    }

    /**
     * Delete the given image from the property. Only the owner may perform this action.
     */
    public function destroy(Property $property, PropertyImage $image, ImageUploadService $imageUploadService): JsonResponse
    {
        $this->authorize('update', $property);

        abort_if($image->property_id !== $property->id, 404);

        $imageUploadService->delete($image->path);

        $image->delete();

        return response()->json(null, 204);
    }
}
