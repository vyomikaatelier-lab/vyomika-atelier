<?php

namespace App\Http\Controllers;

use App\Support\LegalContent;

class LegalController extends Controller
{
    public function privacy()
    {
        return $this->show('privacy');
    }

    public function terms()
    {
        return $this->show('terms');
    }

    public function shipping()
    {
        return $this->show('shipping');
    }

    public function cancellation()
    {
        return $this->show('cancellation');
    }

    public function warranty()
    {
        return $this->show('warranty');
    }

    public function grievance()
    {
        return $this->show('grievance');
    }

    public function show(string $page)
    {
        $data = LegalContent::page($page);
        if (! $data) {
            abort(404);
        }

        return view('pages.legal.show', [
            'pageKey' => $page,
            'page' => $data,
            'sections' => LegalContent::resolvedSections($page),
            'lastUpdated' => $data['content_updated_at'] ?? LegalContent::lastUpdated(),
            'business' => LegalContent::business(),
        ]);
    }
}
