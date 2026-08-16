<?php

namespace App\Http\Controllers\Admin;

use App\Homepage\TemplateRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MYRA v2.6 [D] — demos for the shadcn-vue primitives added in this release.
 *
 * Every payload is a static array. fake() lives in fakerphp/faker, a
 * require-dev package that production installs with --no-dev, so calling it
 * from a controller 500s the page on a real deploy.
 */
class ComponentDemoController extends Controller
{
    public function emptyAndItem(): Response
    {
        Gate::authorize('demo.view');

        return Inertia::render('Admin/Demo/EmptyAndItem', [
            'projects' => [
                ['id' => 'atlas', 'name' => 'Atlas', 'meta' => 'Updated 2 hours ago', 'size' => '48 MB', 'status' => 'active'],
                ['id' => 'beacon', 'name' => 'Beacon', 'meta' => 'Updated yesterday', 'size' => '12 MB', 'status' => 'active'],
                ['id' => 'cinder', 'name' => 'Cinder', 'meta' => 'Updated last week', 'size' => '204 MB', 'status' => 'paused'],
                ['id' => 'delta', 'name' => 'Delta', 'meta' => 'Updated 3 weeks ago', 'size' => '7 MB', 'status' => 'archived'],
            ],
        ]);
    }

    public function chartPrimitives(): Response
    {
        Gate::authorize('demo.view');

        return Inertia::render('Admin/Demo/ChartPrimitives', [
            'monthly' => [
                ['month' => 'Jan', 'desktop' => 186, 'mobile' => 80],
                ['month' => 'Feb', 'desktop' => 305, 'mobile' => 200],
                ['month' => 'Mar', 'desktop' => 237, 'mobile' => 120],
                ['month' => 'Apr', 'desktop' => 173, 'mobile' => 190],
                ['month' => 'May', 'desktop' => 209, 'mobile' => 130],
                ['month' => 'Jun', 'desktop' => 264, 'mobile' => 140],
            ],
            'channels' => [
                ['channel' => 'Organic', 'visitors' => 4210],
                ['channel' => 'Referral', 'visitors' => 2180],
                ['channel' => 'Email', 'visitors' => 1640],
                ['channel' => 'Social', 'visitors' => 980],
            ],
        ]);
    }

    public function otpAndCombobox(): Response
    {
        Gate::authorize('demo.view');

        return Inertia::render('Admin/Demo/OtpAndCombobox', [
            'timezones' => [
                ['value' => 'UTC', 'label' => 'UTC'],
                ['value' => 'Asia/Kuala_Lumpur', 'label' => 'Kuala Lumpur (GMT+8)'],
                ['value' => 'Asia/Singapore', 'label' => 'Singapore (GMT+8)'],
                ['value' => 'Asia/Tokyo', 'label' => 'Tokyo (GMT+9)'],
                ['value' => 'Europe/London', 'label' => 'London (GMT+0)'],
                ['value' => 'Europe/Berlin', 'label' => 'Berlin (GMT+1)'],
                ['value' => 'America/New_York', 'label' => 'New York (GMT-5)'],
                ['value' => 'America/Los_Angeles', 'label' => 'Los Angeles (GMT-8)'],
            ],
        ]);
    }

    public function conversation(): Response
    {
        Gate::authorize('demo.view');

        return Inertia::render('Admin/Demo/Conversation', [
            'thread' => [
                ['id' => 1, 'author' => 'Aisyah Rahman', 'initials' => 'AR', 'side' => 'incoming', 'body' => 'The export finished — 12,480 rows in the archive.', 'at' => '09:12'],
                ['id' => 2, 'author' => 'You', 'initials' => 'ME', 'side' => 'outgoing', 'body' => 'Perfect. Did the schedule pick up the new filter?', 'at' => '09:14'],
                ['id' => 3, 'author' => 'Aisyah Rahman', 'initials' => 'AR', 'side' => 'incoming', 'body' => 'It did. I attached the run log so you can check the compiled rule tree.', 'at' => '09:15'],
                ['id' => 4, 'author' => 'You', 'initials' => 'ME', 'side' => 'outgoing', 'body' => 'Thanks — I will queue the next delivery for Friday.', 'at' => '09:18'],
            ],
            'attachments' => [
                ['id' => 'log', 'name' => 'schedule-run.log', 'size' => '18 KB', 'kind' => 'text'],
                ['id' => 'csv', 'name' => 'orders-2026-08.csv', 'size' => '2.4 MB', 'kind' => 'sheet'],
            ],
        ]);
    }

    public function questionnaire(): Response
    {
        Gate::authorize('demo.view');

        return Inertia::render('Admin/Demo/QuestionnaireDemo', [
            'questions' => [
                [
                    'id' => 'role',
                    'type' => 'choice',
                    'choices' => [
                        ['value' => 'engineer', 'labelKey' => 'gallery.componentDemos.questionnaire.choices.engineer'],
                        ['value' => 'designer', 'labelKey' => 'gallery.componentDemos.questionnaire.choices.designer'],
                        ['value' => 'operator', 'labelKey' => 'gallery.componentDemos.questionnaire.choices.operator'],
                    ],
                ],
                [
                    'id' => 'size',
                    'type' => 'choice',
                    'choices' => [
                        ['value' => 'solo', 'labelKey' => 'gallery.componentDemos.questionnaire.choices.solo'],
                        ['value' => 'small', 'labelKey' => 'gallery.componentDemos.questionnaire.choices.small'],
                        ['value' => 'large', 'labelKey' => 'gallery.componentDemos.questionnaire.choices.large'],
                    ],
                ],
                [
                    'id' => 'goal',
                    'type' => 'text',
                    'choices' => [],
                ],
            ],
        ]);
    }

    public function mapMarkers(): Response
    {
        Gate::authorize('demo.view');

        return Inertia::render('Admin/Demo/MapMarkers', [
            'markers' => [
                ['id' => 'kul', 'name' => 'Kuala Lumpur', 'region' => 'Federal Territory', 'lng' => 101.6869, 'lat' => 3.1390, 'tone' => 'primary'],
                ['id' => 'png', 'name' => 'George Town', 'region' => 'Penang', 'lng' => 100.3293, 'lat' => 5.4141, 'tone' => 'accent'],
                ['id' => 'jhb', 'name' => 'Johor Bahru', 'region' => 'Johor', 'lng' => 103.7414, 'lat' => 1.4927, 'tone' => 'accent'],
                ['id' => 'bki', 'name' => 'Kota Kinabalu', 'region' => 'Sabah', 'lng' => 116.0735, 'lat' => 5.9804, 'tone' => 'muted'],
                ['id' => 'kch', 'name' => 'Kuching', 'region' => 'Sarawak', 'lng' => 110.3592, 'lat' => 1.5535, 'tone' => 'muted'],
            ],
        ]);
    }

    public function landingTemplates(): Response
    {
        Gate::authorize('demo.view');

        return Inertia::render('Admin/Demo/LandingTemplates', [
            'templates' => TemplateRegistry::toClientSchema(),
        ]);
    }
}
