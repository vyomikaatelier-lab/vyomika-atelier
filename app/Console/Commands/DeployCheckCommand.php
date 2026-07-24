<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Support\LandingPageContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DeployCheckCommand extends Command
{
    protected $signature = 'vyomika:deploy-check';

    protected $description = 'Verify production has the latest deploy (save fixes, site_settings, git commit)';

    public function handle(): int
    {
        $this->line('Git commit: (skipped — shell_exec disabled on this host)');
        $this->line('Landing save fix: '.(method_exists(LandingPageContent::class, 'storeOverride') ? 'yes' : 'NO'));
        $this->line('site_settings table: '.(Schema::hasTable('site_settings') ? 'yes' : 'NO'));

        if (Schema::hasTable('site_settings')) {
            $cortenTitle = data_get(SiteSetting::getValue('landing_pages', []), 'corten-steel.hero.title');
            $this->line('Saved Corten hero title in DB: '.($cortenTitle ?: '(none — using config defaults)'));
        }

        $this->line('Responsive hero admin hints: '.(
            is_file(resource_path('views/admin/partials/responsive-hero-images.blade.php'))
            && str_contains((string) file_get_contents(resource_path('views/admin/partials/responsive-hero-images.blade.php')), 'Recommended:')
                ? 'yes'
                : 'NO'
        ));

        return self::SUCCESS;
    }
}
