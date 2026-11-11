<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "One key per device" licenses: instead of one key shared by up to
     * max_devices devices, the license holds max_devices unique keys, each
     * good for exactly one device. A key is available, in use (bound to the
     * device that activated it) or used (its device was released: the key
     * never works again).
     *
     * licenses.key_mode: shared (one key, as before) | per_device.
     * license_keys: the per-device keys; position 1 is licenses.key.
     * license_activations.license_key_id: the key a device activated with.
     *
     * Existing licenses become per_device: their key stays as key 1 (bound
     * to the device already using it, if any) and keys are added up to
     * max_devices.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('key_mode', 20)->default('shared')->after('key');
        });

        Schema::create('license_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('key', 64)->unique();
            $table->string('status', 20)->default('available');
            // The device the key is (or was) bound to.
            $table->string('device_id')->nullable();
            $table->string('device_name')->nullable();
            $table->string('platform', 50)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->unique(['license_id', 'position']);
            $table->index(['license_id', 'status']);
        });

        Schema::table('license_activations', function (Blueprint $table) {
            $table->foreignId('license_key_id')->nullable()->after('license_id')->constrained('license_keys')->nullOnDelete();
        });

        $used = [];
        foreach (DB::table('licenses')->whereNull('deleted_at')->orderBy('id')->get(['id', 'key', 'max_devices']) as $license) {
            DB::table('licenses')->where('id', $license->id)->update(['key_mode' => 'per_device']);

            $activations = DB::table('license_activations')->where('license_id', $license->id)->orderBy('activated_at')->orderBy('id')->get();
            $first = $activations->first();
            $now = now();

            $firstKeyId = DB::table('license_keys')->insertGetId([
                'license_id' => $license->id,
                'position' => 1,
                'key' => $license->key,
                'status' => $first ? 'in_use' : 'available',
                'device_id' => $first?->device_id,
                'device_name' => $first?->device_name,
                'platform' => $first?->platform,
                'activated_at' => $first?->activated_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($first) {
                DB::table('license_activations')->where('id', $first->id)->update(['license_key_id' => $firstKeyId]);
            }

            // Same 4-digit license number as key 1, fresh random groups.
            $head = substr($license->key, 0, strpos($license->key, '-'));
            $tail = substr($license->key, strrpos($license->key, '-') + 1);
            for ($position = 2; $position <= max(1, (int) $license->max_devices); $position++) {
                do {
                    $key = $head.'-'.$this->group().'-'.$this->group().'-'.$this->group().'-'.$tail;
                } while (isset($used[$key]) || DB::table('licenses')->where('key', $key)->exists() || DB::table('license_keys')->where('key', $key)->exists());
                $used[$key] = true;

                DB::table('license_keys')->insert([
                    'license_id' => $license->id,
                    'position' => $position,
                    'key' => $key,
                    'status' => 'available',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('license_activations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('license_key_id');
        });
        Schema::dropIfExists('license_keys');
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('key_mode');
        });
    }

    private function group(): string
    {
        $group = '';
        for ($i = 0; $i < 4; $i++) {
            $group .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $group;
    }
};
