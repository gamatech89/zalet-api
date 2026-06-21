<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /**
     * GET /api/v1/admin/settings
     */
    public function index(): JsonResponse
    {
        $settings = AppSetting::orderBy('key')->get()->map(fn($s) => [
            'key'         => $s->key,
            'value'       => $this->castValue($s),
            'type'        => $s->type,
            'description' => $s->description,
        ]);

        return response()->json(['data' => $settings]);
    }

    /**
     * PUT /api/v1/admin/settings/{key}
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $setting = AppSetting::where('key', $key)->firstOrFail();

        $request->validate([
            'value' => 'required',
        ]);

        $value = $request->input('value');

        // Validate range for known keys
        $this->validateSettingValue($key, $value);

        AppSetting::set($key, $value);
        $setting->refresh();

        return response()->json([
            'data' => [
                'key'         => $setting->key,
                'value'       => $this->castValue($setting),
                'type'        => $setting->type,
                'description' => $setting->description,
            ],
        ]);
    }

    private function castValue(AppSetting $s): mixed
    {
        return match ($s->type) {
            'integer' => (int) $s->value,
            'float'   => (float) $s->value,
            default   => $s->value,
        };
    }

    private function validateSettingValue(string $key, mixed $value): void
    {
        match ($key) {
            'transfer_fee_percent'  => $this->assertRange($value, 0, 50),
            'transfer_min_amount'   => $this->assertRange($value, 1, 10000),
            'gift_creator_percent'  => $this->assertRange($value, 0, 100),
            'ppv_creator_percent'   => $this->assertRange($value, 0, 100),
            'ppv_content_percent'   => $this->assertRange($value, 1, 100),
            'ppv_monthly_limit'     => $this->assertRange($value, 0, 100),
            default                 => null,
        };
    }

    private function assertRange(mixed $value, int $min, int $max): void
    {
        if (!is_numeric($value) || $value < $min || $value > $max) {
            abort(422, "Vrednost mora biti između {$min} i {$max}.");
        }
    }
}
