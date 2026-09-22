<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminStaffUiAssetsTest extends TestCase
{
    public function test_invitation_reveal_css_uses_attached_sticky_two_column_grid(): void
    {
        $css = $this->asset('css/admin-invitation-reveal.css');

        $this->assertStringContainsString('.secure-layout {', $css);
        $this->assertStringContainsString('display: grid;', $css);
        $this->assertStringContainsString('grid-template-columns: 1fr;', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) minmax(22rem, 26rem);', $css);
        $this->assertStringContainsString('grid-template-areas: "main panel";', $css);
        $this->assertStringContainsString('grid-area: panel;', $css);
        $this->assertStringContainsString('position: sticky;', $css);
        $this->assertStringContainsString('box-sizing: border-box;', $css);
        $this->assertStringContainsString('@media (max-width: 899px)', $css);
        $this->assertStringContainsString('@media (min-width: 900px)', $css);
        $this->assertStringContainsString('order: -1;', $css);
        $this->assertStringNotContainsString('position: fixed;', $css);
        $this->assertStringNotContainsString('position:fixed;', $css);
        $this->assertDoesNotMatchRegularExpression('/padding-right:\s*min\(/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.fallback-panel\s*\{[^}]*right:\s*1\.25rem/', $css);
    }

    public function test_invitation_reveal_script_replaces_only_the_clean_staff_url(): void
    {
        $script = $this->asset('js/admin-invitation-reveal.js');

        $this->assertStringContainsString('data-staff-index-url', $script);
        $this->assertStringContainsString('replaceState({}, \'\', staffIndexUrl)', $script);
        $this->assertStringContainsString('isCleanStaffIndexUrl', $script);
        $this->assertStringContainsString('/\\/admin\\/staff\\/invitations\\/?$/', $script);
        $this->assertStringNotContainsString('/\\/admin\\/staff\\/?$/', $script);
        $this->assertStringContainsString("indexOf('token=')", $script);
        $this->assertDoesNotMatchRegularExpression('/replaceState\([^;]*invitation-url/', $script);
        $this->assertDoesNotMatchRegularExpression('/replaceState\([^;]*\.value/', $script);

        foreach ([
            'fetch(',
            'XMLHttpRequest',
            'sendBeacon',
            'navigator.sendBeacon',
            'window.open',
            'location.assign',
            'location.replace',
            'document.location',
            'window.location',
            'console.log',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script);
        }
    }

    public function test_invitation_reveal_layout_versions_local_files_and_hides_missing_paths(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin-invitation-reveal.blade.php'));
        $this->assertIsString($layout);
        $this->assertMatchesRegularExpression(
            '/\$invitationRevealCssVer = @filemtime\(public_path\(\'css\/admin-invitation-reveal\.css\'\)\) \?: time\(\);/',
            $layout
        );
        $this->assertMatchesRegularExpression(
            '/\$invitationRevealJsVer = @filemtime\(public_path\(\'js\/admin-invitation-reveal\.js\'\)\) \?: time\(\);/',
            $layout
        );
        $this->assertStringContainsString("asset('css/admin-invitation-reveal.css') }}?v={{ \$invitationRevealCssVer }}", $layout);
        $this->assertStringContainsString("asset('js/admin-invitation-reveal.js') }}?v={{ \$invitationRevealJsVer }}", $layout);
        $this->assertDoesNotMatchRegularExpression('/\{\{[^}]*public_path\(/', $layout);
        $this->assertStringNotContainsString('request(', $layout);
        $this->assertStringNotContainsString('session(', $layout);
    }

    public function test_role_permission_script_syncs_accessible_switch_state(): void
    {
        $script = $this->asset('js/admin-staff-roles.js');

        $this->assertSame(1, preg_match_all('/function syncSwitchState\s*\(/', $script));
        $this->assertStringContainsString("setAttribute('aria-label'", $script);
        $this->assertStringContainsString("setAttribute('aria-checked'", $script);
        $this->assertStringContainsString('data-switch-role', $script);
        $this->assertStringContainsString('data-switch-permission', $script);
        $this->assertStringContainsString('data-switch-lock', $script);
        $this->assertStringContainsString("addEventListener('change'", $script);
        $this->assertStringContainsString("addEventListener('reset'", $script);
        $this->assertStringContainsString('forEach(syncSwitchState)', $script);
        $this->assertStringContainsString('syncSwitchState(input)', $script);
        $this->assertStringContainsString("staff-switch-state", $script);
        $this->assertMatchesRegularExpression("/', locked\. '/", $script);
    }

    private function asset(string $relative): string
    {
        $path = public_path($relative);
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
