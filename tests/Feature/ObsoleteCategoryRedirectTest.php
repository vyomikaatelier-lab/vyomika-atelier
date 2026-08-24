<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObsoleteCategoryRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_home_decor_redirects_to_shop_index(): void
    {
        $this->get('/shop/home-decor')
            ->assertRedirect(route('shop.index'));
    }

    public function test_shop_fluted_panels_redirects_to_studio_partitions(): void
    {
        $this->get('/shop/fluted-panels')
            ->assertRedirect(route('studio.show', 'pvd-partitions'));
    }

    public function test_shop_railings_redirects_to_railings_index(): void
    {
        $this->get('/shop/railings')
            ->assertRedirect(route('railings.index'));
    }

    public function test_corten_steel_is_canonical_and_service_url_redirects(): void
    {
        $this->get('/corten-steel')->assertOk();

        $this->get('/services/corten-steel-facade')
            ->assertRedirect('/corten-steel');
    }

    public function test_services_index_redirects_to_shop(): void
    {
        $this->get('/services')->assertRedirect('/shop');
    }

    public function test_legacy_service_slug_redirects_to_studio_or_shop(): void
    {
        $this->get('/services/partitions')
            ->assertRedirect(route('studio.show', 'pvd-partitions'));

        $this->get('/services/fluted-screens')
            ->assertRedirect('/shop');
    }
}
