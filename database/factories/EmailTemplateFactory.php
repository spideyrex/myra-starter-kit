<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(2),
            'subject' => fake()->sentence(),
            'body_html' => '<h1>' . fake()->sentence() . '</h1><p>' . fake()->paragraph() . '</p>',
            'variables' => ['name', 'app_name'],
        ];
    }
}
