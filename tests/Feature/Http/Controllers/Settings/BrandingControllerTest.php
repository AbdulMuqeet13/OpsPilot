<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function validBranding(array $overrides = []): array
{
    return [
        'brand_name' => 'Harbor & Pine',
        'primary_color' => '#0F766E',
        'support_email' => 'care@harborpine.example',
        ...$overrides,
    ];
}

describe('edit', function () {
    it('redirects guests to sign in', function () {
        get(route('branding.edit'))->assertRedirect(route('login'));
    });

    it('forbids agents', function () {
        actingAs(User::factory()->agent()->create())
            ->get(route('branding.edit'))
            ->assertForbidden();
    });

    it('shows admins the current branding', function () {
        $admin = User::factory()->create();

        actingAs($admin)
            ->get(route('branding.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/branding')
                ->where('branding.brand_name', $admin->workspace->brand_name)
                ->where('branding.primary_color', $admin->workspace->primary_color));
    });
});

describe('update', function () {
    it('forbids agents', function () {
        $agent = User::factory()->agent()->create();

        actingAs($agent)
            ->patch(route('branding.update'), validBranding())
            ->assertForbidden();

        expect($agent->workspace->fresh()->brand_name)->not->toBe('Harbor & Pine');
    });

    it('saves the brand and shares it with every page', function () {
        $admin = User::factory()->create();

        actingAs($admin)
            ->patch(route('branding.update'), validBranding())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($admin->workspace->fresh())
            ->brand_name->toBe('Harbor & Pine')
            ->primary_color->toBe('#0f766e')
            ->support_email->toBe('care@harborpine.example');

        actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('brand.name', 'Harbor & Pine')
                ->where('brand.primaryColor', '#0f766e')
                ->where('brand.primaryForegroundColor', '#ffffff'));
    });

    it('only changes the admin\'s own workspace', function () {
        $admin = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create(['brand_name' => 'Untouched']);

        actingAs($admin)->patch(route('branding.update'), validBranding());

        expect($otherWorkspace->fresh()->brand_name)->toBe('Untouched');
    });

    it('stores an uploaded logo and deletes the previous one', function () {
        Storage::fake('public');
        $admin = User::factory()->create();

        actingAs($admin)->patch(route('branding.update'), validBranding([
            'logo' => UploadedFile::fake()->image('first.png', 200, 200),
        ]));
        $firstLogoPath = $admin->workspace->fresh()->logo_path;

        actingAs($admin)->patch(route('branding.update'), validBranding([
            'logo' => UploadedFile::fake()->image('second.png', 200, 200),
        ]));
        $secondLogoPath = $admin->workspace->fresh()->logo_path;

        Storage::disk('public')->assertMissing($firstLogoPath);
        Storage::disk('public')->assertExists($secondLogoPath);
    });

    it('removes the logo when asked', function () {
        Storage::fake('public');
        $admin = User::factory()->create();
        actingAs($admin)->patch(route('branding.update'), validBranding([
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]));
        $logoPath = $admin->workspace->fresh()->logo_path;

        actingAs($admin)->patch(route('branding.update'), validBranding(['remove_logo' => true]));

        expect($admin->workspace->fresh()->logo_path)->toBeNull();
        Storage::disk('public')->assertMissing($logoPath);
    });

    it('rejects a logo that is not a raster image', function () {
        Storage::fake('public');

        actingAs(User::factory()->create())
            ->patch(route('branding.update'), validBranding([
                'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            ]))
            ->assertSessionHasErrors('logo');
    });

    it('rejects a logo above the size limit', function () {
        Storage::fake('public');
        config(['opspilot.logo.max_kilobytes' => 100]);

        actingAs(User::factory()->create())
            ->patch(route('branding.update'), validBranding([
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200)->size(101),
            ]))
            ->assertSessionHasErrors(['logo' => 'The logo field must not be greater than 100 kilobytes.']);
    });

    it('rejects a primary color that is not a 6-digit hex value', function (string $color) {
        actingAs(User::factory()->create())
            ->patch(route('branding.update'), validBranding(['primary_color' => $color]))
            ->assertSessionHasErrors(['primary_color' => 'Use a 6-digit hex color, for example #4f46e5.']);
    })->with([
        'named color' => 'red',
        'short hex' => '#fff',
        'css injection' => '#fff;}body{display:none',
    ]);

    it('requires a brand name', function () {
        actingAs(User::factory()->create())
            ->patch(route('branding.update'), validBranding(['brand_name' => '']))
            ->assertSessionHasErrors(['brand_name' => 'The brand name field is required.']);
    });
});
