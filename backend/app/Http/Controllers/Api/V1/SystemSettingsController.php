<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\System\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSystemSettingRequest;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    public function store(UpdateSystemSettingRequest $request, SettingsService $settings): JsonResponse
    {
        $data = $request->validated();
        abort_unless(isset($data['key']) && preg_match('/^[a-zA-Z0-9._-]{1,120}$/', $data['key']) === 1, 422, 'Setting key has an invalid format.');
        abort_unless($request->user()->hasPermission('system.settings.update', $data['scope_type'], $data['scope_id']), 403);
        $record = $settings->update($data['key'], $data['scope_type'], $data['scope_id'], $data['value'], $data['value_type'], $data['reason'], $data['expected_version'], $request->user()->id, $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Setting version recorded.', 'data' => [
            'key' => $record->setting_key, 'scope_type' => $record->scope_type, 'scope_id' => $record->scope_id,
            'value' => $record->value_json, 'type' => $record->value_type, 'version' => $record->version,
        ], 'meta' => ['request_id' => $request->attributes->get('request_id')]], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $hasGlobal = $actor->hasPermission('system.settings.view');
        $scopes = $actor->roles()->wherePivot('status', 'active')->wherePivot('effective_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('iam_user_role_assignments.effective_to')->orWhere('iam_user_role_assignments.effective_to', '>', now());
            })
            ->whereHas('permissions', fn ($query) => $query->where('code', 'system.settings.view'))
            ->get()->map(fn ($role) => [(string) $role->pivot->scope_type, (string) $role->pivot->scope_id])->unique()->values();
        abort_unless($hasGlobal || $scopes->isNotEmpty(), 403);
        $settingsQuery = SystemSetting::query()->where('status', 'active')->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->when(! $hasGlobal, function ($query) use ($scopes): void {
                $query->where(function ($visible) use ($scopes): void {
                    $visible->where(fn ($global) => $global->where('scope_type', 'global')->where('scope_id', 'global'));
                    foreach ($scopes as [$type, $id]) {
                        if ($type !== 'global') {
                            $visible->orWhere(fn ($scope) => $scope->where('scope_type', $type)->where('scope_id', $id));
                        }
                    }
                });
            });
        $settings = $settingsQuery->orderBy('setting_key')->paginate(min(max((int) $request->integer('per_page', 25), 1), 100));
        $settings->getCollection()->transform(fn (SystemSetting $setting) => [
            'key' => $setting->setting_key, 'scope_type' => $setting->scope_type, 'scope_id' => $setting->scope_id,
            'value' => preg_match('/(secret|password|passwd|token|credential|api[_-]?key)/i', $setting->setting_key) ? '[REDACTED]' : $setting->value_json,
            'type' => $setting->value_type, 'version' => $setting->version,
            'effective_from' => $setting->effective_from,
        ]);

        return response()->json(['success' => true, 'message' => 'Active settings retrieved.', 'data' => $settings, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function update(UpdateSystemSettingRequest $request, string $key, SettingsService $settings): JsonResponse
    {
        abort_unless(preg_match('/^[a-zA-Z0-9._-]{1,120}$/', $key) === 1, 422, 'Setting key has an invalid format.');
        abort_unless($request->user()->hasPermission('system.settings.update', $request->string('scope_type')->toString(), $request->string('scope_id')->toString()), 403);
        $record = $settings->update($key, $request->string('scope_type')->toString(), $request->string('scope_id')->toString(),
            $request->input('value'), $request->string('value_type')->toString(), $request->string('reason')->toString(),
            $request->integer('expected_version'), $request->user()->id, $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Setting version recorded.', 'data' => [
            'key' => $record->setting_key, 'scope_type' => $record->scope_type, 'scope_id' => $record->scope_id,
            'value' => $record->value_json, 'type' => $record->value_type, 'version' => $record->version,
        ], 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }
}
