<?php

/**
 * Заполнить errors_count для старых снимков (по одной записи, без OOM в списке).
 *
 * php scripts/backfill-meta-tags-errors-count.php
 * php scripts/backfill-meta-tags-errors-count.php --project=53
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$projectId = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--project=') === 0) {
        $projectId = (int) substr($arg, 10);
    }
}

$query = App\MetaTagsHistory::query()->whereNull('errors_count')->orderBy('id');

if ($projectId) {
    $query->where('meta_tag_id', $projectId);
}

$total = (clone $query)->count();
echo "Pending: {$total}\n";

$query->select(['id', 'data'])->orderBy('id')->each(function ($row) {
    $errors = json_decode($row->data);
    $count = 0;
    if (is_array($errors) || $errors instanceof Traversable) {
        foreach ($errors as $e) {
            if (!isset($e->error)) {
                continue;
            }
            $arr = Illuminate\Support\Arr::flatten($e->error->badge ?? []);
            if (is_array($arr)) {
                $count += count($arr);
            }
        }
    }
    App\MetaTagsHistory::query()->whereKey($row->id)->update(['errors_count' => $count]);
    echo "id={$row->id} errors={$count}\n";
    unset($row, $errors);
});

echo "Done.\n";
