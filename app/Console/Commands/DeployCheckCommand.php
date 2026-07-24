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
            $row = SiteSetting::query()->where('key', 'landing_pages')->first();
            $cortenTitle = data_get($row?->value, 'corten-steel.hero.title');
            $defaultTitle = (string) config('corten.hero.title', '');
            $this->line('Saved Corten hero title in DB: '.($cortenTitle ?: '(none — using config defaults)'));
            if ($cortenTitle && $defaultTitle !== '' && $cortenTitle === $defaultTitle) {
                $this->line('Corten title note: matches config default — change hero title to TEST SAVE 123 in admin to confirm saves work.');
            }
            if ($row?->updated_at) {
                $this->line('landing_pages last updated: '.$row->updated_at->toDateTimeString());
            }
        }

        $this->line('PHP post_max_size: '.ini_get('post_max_size'));
        $this->line('PHP upload_max_filesize: '.ini_get('upload_max_filesize'));
        $this->line('PHP max_input_vars: '.ini_get('max_input_vars'));

        $this->line('Responsive hero admin hints: '.(
            is_file(resource_path('views/admin/partials/responsive-hero-images.blade.php'))
            && str_contains((string) file_get_contents(resource_path('views/admin/partials/responsive-hero-images.blade.php')), 'Recommended:')
                ? 'yes'
                : 'NO'
        ));

        return self::SUCCESS;
    }
}
