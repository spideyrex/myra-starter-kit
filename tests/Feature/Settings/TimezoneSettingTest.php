<?php

namespace Tests\Feature\Settings;

use App\Settings\GeneralSettings;
use App\Support\Myra;
use App\Support\Timezones;
use Tests\TestCase;

/**
 * The timezone is a picker backed by the IANA list, and the stored value is
 * checked server-side — a <select> narrows what a browser offers, it does not
 * constrain what a request may send.
 */
class TimezoneSettingTest extends TestCase
{
    private function generalPayload(array $overrides = []): array
    {
        $settings = app(GeneralSettings::class);

        return array_merge([
            'site_name' => $settings->site_name,
            'site_description' => $settings->site_description,
            'site_url' => $settings->site_url,
            'admin_email' => $settings->admin_email,
            'timezone' => $settings->timezone,
            'login_tagline' => $settings->login_tagline,
            'registration_enabled' => $settings->registration_enabled,
        ], $overrides);
    }

    public function test_the_page_ships_the_timezone_options(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('admin.settings.index'));
        $response->assertOk();

        $timezones = json_decode(json_encode($response->viewData('page')['props']['timezones'] ?? null), true);

        $this->assertIsArray($timezones);
        $this->assertGreaterThan(300, count($timezones), 'The full IANA list should ship, not a curated handful.');
        $this->assertSame(['value', 'label'], array_keys($timezones[0]), 'The shape FormField reads is {value,label}.');

        $values = array_column($timezones, 'value');
        $this->assertContains('UTC', $values);
        $this->assertContains('Asia/Kuala_Lumpur', $values);

        // The stored value must be selectable, or the control renders blank.
        $this->assertContains(app(GeneralSettings::class)->timezone, $values);
    }

    /** reka-ui rejects an empty option value, and a blank label is unpickable. */
    public function test_no_option_is_empty_or_duplicated(): void
    {
        $options = Timezones::options();
        $values = array_column($options, 'value');

        foreach ($options as $option) {
            $this->assertNotSame('', $option['value']);
            $this->assertNotSame('', $option['label']);
        }

        $this->assertSame(count($values), count(array_unique($values)));
    }

    public function test_labels_carry_the_offset_and_options_run_west_to_east(): void
    {
        $options = Timezones::options();

        $this->assertMatchesRegularExpression('~^\(UTC[+-]\d{2}:\d{2}\) ~', $options[0]['label']);

        $offsets = array_map(fn (array $o) => Timezones::offset($o['value']), $options);
        $sorted = $offsets;
        sort($sorted);

        $this->assertSame($sorted, $offsets, 'Options should be ordered by UTC offset.');
    }

    public function test_offset_formatting_handles_negative_and_half_hour_zones(): void
    {
        $this->assertSame('+00:00', Timezones::formatOffset(0));
        $this->assertSame('+08:00', Timezones::formatOffset(8 * 3600));
        $this->assertSame('-03:30', Timezones::formatOffset(-(3 * 3600 + 30 * 60)));
        $this->assertSame('+05:45', Timezones::formatOffset(5 * 3600 + 45 * 60));
    }

    public function test_a_valid_timezone_is_saved(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(Myra::adminPath('settings/general'), $this->generalPayload(['timezone' => 'Asia/Kuala_Lumpur']))
            ->assertRedirect();

        app()->forgetInstance(GeneralSettings::class);

        $this->assertSame('Asia/Kuala_Lumpur', app(GeneralSettings::class)->timezone);
    }

    /**
     * The regression this guards: the general group writes request input
     * straight onto the settings object, so without an explicit rule any
     * string reached the property that drives date rendering.
     */
    public function test_a_timezone_outside_the_iana_list_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $before = app(GeneralSettings::class)->timezone;

        foreach (['Mars/Olympus_Mons', 'not-a-zone', '', 'UTC+8'] as $bogus) {
            $this->put(Myra::adminPath('settings/general'), $this->generalPayload(['timezone' => $bogus]))
                ->assertSessionHasErrors('timezone');
        }

        app()->forgetInstance(GeneralSettings::class);

        $this->assertSame($before, app(GeneralSettings::class)->timezone, 'A rejected request must not mutate the setting.');
    }

    public function test_is_valid_agrees_with_the_shipped_options(): void
    {
        $this->assertTrue(Timezones::isValid('UTC'));
        $this->assertTrue(Timezones::isValid('America/New_York'));
        $this->assertFalse(Timezones::isValid(''));
        $this->assertFalse(Timezones::isValid('Mars/Olympus_Mons'));

        foreach (array_column(Timezones::options(), 'value') as $value) {
            $this->assertTrue(Timezones::isValid($value), "{$value} is offered but rejected by isValid().");
        }
    }
}
