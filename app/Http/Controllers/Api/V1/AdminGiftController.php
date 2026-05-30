<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGiftRequest;
use App\Http\Requests\UpdateGiftRequest;
use App\Models\Gift;
use App\Models\GiftCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminGiftController extends Controller
{
    // ═══════════════════════════════════════════════════════════════
    //  GIFTS
    // ═══════════════════════════════════════════════════════════════

    /**
     * List all gifts (including inactive) with pagination.
     * GET /api/v1/admin/gifts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Gift::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $gifts = $query->orderBy('sort_order')
            ->orderBy('coin_price')
            ->paginate($request->integer('per_page', 20));

        return response()->json($gifts);
    }

    /**
     * Create a new gift.
     * POST /api/v1/admin/gifts
     */
    public function store(StoreGiftRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Handle icon uploads
        if ($request->hasFile('icon_2d')) {
            $data['icon_2d'] = $this->storeIcon($request->file('icon_2d'), '2d');
        }

        if ($request->hasFile('icon_3d')) {
            $data['icon_3d'] = $this->storeIcon($request->file('icon_3d'), '3d');
        }

        $gift = Gift::create($data);
        $gift->load('category');

        $this->clearGiftCache();

        return response()->json([
            'message' => 'Gift created successfully.',
            'data' => $gift,
        ], 201);
    }

    /**
     * Get a single gift.
     * GET /api/v1/admin/gifts/{gift}
     */
    public function show(Gift $gift): JsonResponse
    {
        $gift->load('category');

        return response()->json([
            'data' => $gift,
        ]);
    }

    /**
     * Update a gift.
     * PUT /api/v1/admin/gifts/{gift}
     */
    public function update(UpdateGiftRequest $request, Gift $gift): JsonResponse
    {
        $data = $request->validated();

        // Handle icon uploads
        if ($request->hasFile('icon_2d')) {
            // Delete old icon
            if ($gift->icon_2d) {
                Storage::disk('public')->delete($gift->icon_2d);
            }
            $data['icon_2d'] = $this->storeIcon($request->file('icon_2d'), '2d');
        }

        if ($request->hasFile('icon_3d')) {
            if ($gift->icon_3d) {
                Storage::disk('public')->delete($gift->icon_3d);
            }
            $data['icon_3d'] = $this->storeIcon($request->file('icon_3d'), '3d');
        }

        $gift->update($data);
        $gift->load('category');

        $this->clearGiftCache();

        return response()->json([
            'message' => 'Gift updated successfully.',
            'data' => $gift,
        ]);
    }

    /**
     * Delete (deactivate) a gift.
     * DELETE /api/v1/admin/gifts/{gift}
     */
    public function destroy(Gift $gift): JsonResponse
    {
        $gift->update(['is_active' => false]);

        $this->clearGiftCache();

        return response()->json([
            'message' => 'Gift deactivated successfully.',
        ]);
    }

    /**
     * Upload or replace an icon for a gift.
     * POST /api/v1/admin/gifts/{gift}/icon
     */
    public function uploadIcon(Request $request, Gift $gift): JsonResponse
    {
        $request->validate([
            'icon' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
            'type' => 'required|in:2d,3d',
        ]);

        $type = $request->input('type');
        $field = "icon_{$type}";

        // Delete old icon
        if ($gift->$field) {
            Storage::disk('public')->delete($gift->$field);
        }

        $path = $this->storeIcon($request->file('icon'), $type);
        $gift->update([$field => $path]);

        $this->clearGiftCache();

        return response()->json([
            'message' => "Icon ({$type}) uploaded successfully.",
            'data' => [
                'field' => $field,
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ],
        ]);
    }

    /**
     * Bulk reorder gifts.
     * PATCH /api/v1/admin/gifts/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:gift_catalog,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->input('items') as $item) {
            Gift::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $this->clearGiftCache();

        return response()->json([
            'message' => 'Gifts reordered successfully.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  GIFT CATEGORIES
    // ═══════════════════════════════════════════════════════════════

    /**
     * List all gift categories.
     * GET /api/v1/admin/gift-categories
     */
    public function categories(): JsonResponse
    {
        $categories = GiftCategory::withCount('gifts')
            ->ordered()
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Create a gift category.
     * POST /api/v1/admin/gift-categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $category = GiftCategory::create([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => $category,
        ], 201);
    }

    /**
     * Update a gift category.
     * PUT /api/v1/admin/gift-categories/{giftCategory}
     */
    public function updateCategory(Request $request, GiftCategory $giftCategory): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['name', 'sort_order', 'is_active']);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $giftCategory->update($data);

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => $giftCategory,
        ]);
    }

    /**
     * Delete a gift category.
     * DELETE /api/v1/admin/gift-categories/{giftCategory}
     */
    public function destroyCategory(GiftCategory $giftCategory): JsonResponse
    {
        // Null out category_id on associated gifts
        Gift::where('category_id', $giftCategory->id)->update(['category_id' => null]);

        $giftCategory->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Store an icon file and return the relative path.
     */
    private function storeIcon(\Illuminate\Http\UploadedFile $file, string $type): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs("gifts/{$type}", $filename, 'public');
    }

    /**
     * Clear the gift catalog cache.
     */
    private function clearGiftCache(): void
    {
        Cache::forget('gifts.catalog');
    }
}