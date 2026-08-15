<?php

namespace App\Admin\Report\Reports;

use App\Admin\QueryBuilder\Operator;
use App\Admin\Report\Bucket;
use App\Admin\Report\ComparisonPeriod;
use App\Admin\Report\Dimension;
use App\Admin\Report\Measure;
use App\Admin\Report\PeriodPreset;
use App\Admin\Report\ReportDefinition;
use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

final class UsersReport
{
    public static function definition(): ReportDefinition
    {
        return ReportDefinition::make('users')
            ->model(User::class)
            ->titleKey('reports.users.title')
            ->permission('reports.view')
            ->scope(fn (Builder $q, ?Authenticatable $actor) => $q->when(
                ! ($actor?->hasRole(config('shield.super_admin_role', 'super-admin')) ?? false),
                fn (Builder $q) => $q->where('created_by', $actor?->getAuthIdentifier()),
            ))
            ->dateColumn('created_at')
            ->dimensions([
                Dimension::date('created_at')->labelKey('reports.dim.signupDate')
                    ->bucket(Bucket::Day)
                    ->allowedBuckets([Bucket::Day, Bucket::Week, Bucket::Month, Bucket::Quarter])
                    ->drillsInto('created_at', Operator::DateBetween),
                Dimension::field('status')->labelKey('reports.dim.status')
                    ->limit(10)->other()
                    ->drillsInto('status', Operator::In),
                Dimension::relation('role')->labelKey('reports.dim.role')
                    ->relationship('roles', 'name')
                    ->limit(10)->other()
                    ->drillsInto('roles', Operator::RelatedTo),
            ])
            ->measures([
                Measure::count('signups')->labelKey('reports.measure.signups'),
                Measure::countDistinct('emails', 'email')->labelKey('reports.measure.uniqueEmails'),
            ])
            ->filters(UserController::filterFieldSet())
            ->defaults('created_at', ['signups'], PeriodPreset::Last30Days)
            ->comparisons([ComparisonPeriod::Previous, ComparisonPeriod::Year])
            ->drillTo('admin.users.index', 'query')
            ->maxGroups((int) config('myra.reports.max_groups', 200))
            ->cacheFor(0)
            ->chart('area')
            ->formats(['csv', 'xlsx', 'pdf'])
            ->schedulable();
    }
}
