<?php

namespace App\Admin\Report\Reports;

use App\Admin\QueryBuilder\FieldSet;
use App\Admin\QueryBuilder\FieldSpec;
use App\Admin\Report\Bucket;
use App\Admin\Report\ComparisonPeriod;
use App\Admin\Report\Dimension;
use App\Admin\Report\Measure;
use App\Admin\Report\PeriodPreset;
use App\Admin\Report\ReportDefinition;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

final class ActivityReport
{
    public static function definition(): ReportDefinition
    {
        return ReportDefinition::make('activity')
            ->model(Activity::class)
            ->titleKey('reports.activity.title')
            ->permission('reports.view')
            ->scope(fn (Builder $q, ?Authenticatable $actor) => $q->when(
                ! ($actor?->hasRole(config('shield.super_admin_role', 'super-admin')) ?? false),
                fn (Builder $q) => $q->where('causer_id', $actor?->getAuthIdentifier()),
            ))
            ->dateColumn('created_at')
            ->dimensions([
                Dimension::date('created_at')->labelKey('reports.dim.eventDate')
                    ->bucket(Bucket::Day)
                    ->allowedBuckets([Bucket::Hour, Bucket::Day, Bucket::Week, Bucket::Month]),
                Dimension::field('subject_type')->labelKey('reports.dim.subjectType')
                    ->limit(10)->other(),
                Dimension::field('description')->labelKey('reports.dim.event')
                    ->limit(10)->other(),
            ])
            ->measures([
                Measure::count('events')->labelKey('reports.measure.events'),
                Measure::countDistinct('actors', 'causer_id')->labelKey('reports.measure.actors'),
            ])
            ->filters(FieldSet::make([
                FieldSpec::text('description')->labelKey('reports.field.event')->contains(),
                FieldSpec::text('subject_type')->labelKey('reports.field.subjectType')->contains(),
                FieldSpec::date('created_at')->labelKey('filters.field.createdAt'),
            ])->maxRules((int) config('myra.filters.max_rules', 25))
                ->maxDepth((int) config('myra.filters.max_depth', 3)))
            ->defaults('created_at', ['events'], PeriodPreset::Last30Days)
            ->comparisons([ComparisonPeriod::Previous])
            ->maxGroups((int) config('myra.reports.max_groups', 200))
            ->cacheFor(0)
            ->chart('bar')
            ->formats(['csv', 'xlsx'])
            ->schedulable();
    }
}
