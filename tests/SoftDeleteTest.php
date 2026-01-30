<?php

use Illuminate\Support\Facades\DB;
use Kangangga\Json\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class SoftDeletedModel extends Model
{
    use SoftDeletes;
    protected $table = 'soft_users';
    protected $guarded = [];
}

test('model supports soft deletes flow', function () {
    $table = 'soft_users';

    // Clean start
    try {
        DB::table($table)->truncate();
    } catch (\Throwable $e) {
    }

    // 1. Create Model
    $model = SoftDeletedModel::create(['name' => 'Ghost User']);
    $id = $model->id;

    // Verify created
    expect($model->deleted_at)->toBeNull();
    expect(SoftDeletedModel::count())->toBe(1);

    // 2. Soft Delete
    $model->delete();

    // Verify logically deleted
    expect(SoftDeletedModel::count())->toBe(0);
    expect(SoftDeletedModel::find($id))->toBeNull();

    // Verify physically exists (raw DB check)
    $raw = DB::table($table)->where('id', $id)->first();
    expect($raw)->not->toBeNull();
    // deleted_at should be set (array access for raw)
    $deletedAt = is_array($raw) ? $raw['deleted_at'] : $raw->deleted_at;
    expect($deletedAt)->not->toBeNull();

    // 3. Query with trashed
    $trashed = SoftDeletedModel::withTrashed()->find($id);
    expect($trashed)->not->toBeNull();
    expect($trashed->id)->toBe($id);

    // 4. Restore
    $trashed->restore();
    expect(SoftDeletedModel::count())->toBe(1);
    expect(SoftDeletedModel::find($id))->not->toBeNull();

    // 5. Force Delete
    $trashed->forceDelete();
    // Verify physically gone
    $rawFinal = DB::table($table)->where('id', $id)->first();
    expect($rawFinal)->toBeNull();
});
