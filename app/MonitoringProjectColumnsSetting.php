<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MonitoringProjectColumnsSetting extends Model
{
    protected $fillable = ['monitoring_project_id', 'name', 'state'];
    protected $table = "monitoring_project_columns";

    /** @var array<string, bool> */
    public const DEFAULT_VISIBILITY = [
        'query' => true,
        'url' => false,
        'group' => true,
        'target_url' => false,
        'target' => true,
        'dynamics' => true,
        'base' => false,
        'phrasal' => false,
        'exact' => false,
    ];

    /**
     * @return array<string, bool>
     */
    public static function visibilityMapForProject(int $projectId): array
    {
        $map = self::DEFAULT_VISIBILITY;

        $saved = self::query()
            ->where('monitoring_project_id', $projectId)
            ->pluck('state', 'name');

        foreach ($saved as $name => $state) {
            if (array_key_exists($name, $map)) {
                $map[$name] = (bool) $state;
            }
        }

        return $map;
    }
}
