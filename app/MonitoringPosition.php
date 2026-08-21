<?php

namespace App;

use App\Classes\Monitoring\MonitoringPositionDates;
use App\Classes\Monitoring\MonitoringTableResponseCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MonitoringPosition extends Model
{
    protected $fillable = ['monitoring_searchengine_id', 'position', 'url', 'target', 'created_at', 'updated_at'];

    protected static function boot()
    {
        parent::boot();

        static::created(static function (MonitoringPosition $position) {
            $engineId = (int) $position->monitoring_searchengine_id;
            if ($engineId < 1) {
                return;
            }
            $date = $position->created_at ?: Carbon::now();
            MonitoringPositionDates::remember($engineId, $date);
            MonitoringTableResponseCache::bumpForEngine($engineId);
        });
    }

    public function engine()
    {
        return $this->belongsTo(MonitoringSearchengine::class, 'monitoring_searchengine_id');
    }

    public function keyword()
    {
        return $this->belongsTo(MonitoringKeyword::class, 'monitoring_keyword_id');
    }

    public function getDateAttribute()
    {
        return $this->created_at->format('d.m.Y');
    }

    public function scopeDateRange($query, array $dates = null)
    {
        $start = Carbon::now()->subMonth()->startOfDay();
        $end = Carbon::now()->endOfDay();

        if ($dates) {
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
        }

        // Без DATE(created_at): иначе индекс не используется и /monitoring/{id}/table сканирует всю таблицу.
        return $query->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end);
    }

    public function scopeDateFind($query, array $dates = null)
    {
        $start = Carbon::parse($dates[0])->startOfDay();
        $end = Carbon::parse($dates[1])->endOfDay();

        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('created_at', [$start, $start->copy()->endOfDay()])
                ->orWhereBetween('created_at', [$end->copy()->startOfDay(), $end]);
        });
    }

    public function scopeWhereEngine($query, int $id)
    {
        return $query->where('monitoring_searchengine_id', $id);
    }
}
