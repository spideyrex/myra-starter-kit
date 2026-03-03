<?php

namespace Database\Factories;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['sent', 'failed', 'queued']);

        return [
            'to' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'template_slug' => fake()->randomElement(['welcome', 'password-reset', 'email-verification', null]),
            'status' => $status,
            'sent_at' => $status === 'sent' ? fake()->dateTimeThisMonth() : null,
            'error' => $status === 'failed' ? fake()->sentence() : null,
        ];
    }
}
