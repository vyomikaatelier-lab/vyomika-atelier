@extends('layouts.admin')
@section('title', 'Edit '.$label)
@section('content')
@php
    use App\Support\MediaUrl;
    $isRailings = $slug === 'railings';
    $isCorten = $slug === 'corten-steel';
    $preview = fn (?string $path) => MediaUrl::resolve($path) ?? $path;
    $lines = fn ($value) => is_array($value) ? implode("\n", $value) : (string) ($value ?? '');
@endphp
<div class="mb-4 flex flex-wrap gap-3 items-center justify-between">
    <a href="{{ route('admin.independent-pages.index') }}" class="text-sm text-blue-600">← Back</a>
    <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="text-sm border px-3 py-1.5 rounded">Preview public page ↗</a>
</div>
<h1 class="text-2xl font-semibold mb-2">{{ $label }}</h1>
@if(request('saved') || session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') ?: 'Page saved successfully.' }}</div>
@endif
<p class="text-sm text-gray-600 mb-6">Edit every section shown on the public page. Images accept upload or URL. Inactive cards are hidden on the storefront.</p>

<form method="POST" action="{{ route('admin.independent-pages.update', $slug) }}" enctype="multipart/form-data" class="space-y-4 max-w-4xl" id="landing-page-form">
    @csrf @method('PUT')
    <input type="hidden" name="_landing_save" value="1">

    <details class="bg-white rounded shadow p-4" open>
        <summary class="font-medium cursor-pointer">SEO</summary>
        <div class="mt-4 space-y-3">
            <div class="grid md:grid-cols-2 gap-3">
                <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $page['meta_title'] ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
                <div><label class="block text-sm mb-1">Slug</label><input value="{{ $slug }}" disabled class="w-full border px-3 py-2 rounded bg-gray-50 text-gray-500"></div>
            </div>
            <div><label class="block text-sm mb-1">Meta description</label><textarea name="meta_description" rows="2" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $page['meta_description'] ?? '') }}</textarea></div>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4" open>
        <summary class="font-medium cursor-pointer">1. Hero cover photo (desktop, tablet &amp; mobile)</summary>
        <div class="mt-4 space-y-3">
            <input name="hero_label" value="{{ old('hero_label', data_get($page, 'hero.label')) }}" placeholder="Eyebrow / label" class="w-full border px-3 py-2 rounded">
            <input name="hero_title" value="{{ old('hero_title', data_get($page, 'hero.title')) }}" placeholder="Hero heading" class="w-full border px-3 py-2 rounded">
            <textarea name="hero_subtitle" rows="2" placeholder="Hero description" class="w-full border px-3 py-2 rounded">{{ old('hero_subtitle', data_get($page, 'hero.subtitle')) }}</textarea>
            <input name="hero_image_alt" value="{{ old('hero_image_alt', data_get($page, 'hero.image_alt')) }}" placeholder="Hero image alt text" class="w-full border px-3 py-2 rounded">
            <p class="text-xs text-gray-500">{{ \App\Support\ResponsiveHero::adminUploadIntro() }}</p>
            @include('admin.partials.responsive-hero-images', ['prefix' => 'hero', 'hero' => data_get($page, 'hero', [])])
            <textarea name="hero_highlights" rows="3" placeholder="Hero highlights (one per line)" class="w-full border px-3 py-2 rounded">{{ old('hero_highlights', $lines(data_get($page, 'hero.highlights'))) }}</textarea>
            <div class="grid md:grid-cols-2 gap-3">
                <input name="hero_cta_primary_label" value="{{ old('hero_cta_primary_label', data_get($page, 'hero.cta_primary.label')) }}" placeholder="Primary CTA label" class="w-full border px-3 py-2 rounded">
                <input name="hero_cta_primary_href" value="{{ old('hero_cta_primary_href', data_get($page, 'hero.cta_primary.href')) }}" placeholder="Primary CTA URL / #anchor" class="w-full border px-3 py-2 rounded">
                <input name="hero_cta_secondary_label" value="{{ old('hero_cta_secondary_label', data_get($page, 'hero.cta_secondary.label')) }}" placeholder="Secondary CTA label" class="w-full border px-3 py-2 rounded">
                <input name="hero_cta_secondary_href" value="{{ old('hero_cta_secondary_href', data_get($page, 'hero.cta_secondary.href')) }}" placeholder="Secondary CTA URL / #anchor" class="w-full border px-3 py-2 rounded">
            </div>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4" open>
        <summary class="font-medium cursor-pointer">2. Introduction</summary>
        <div class="mt-4 space-y-3">
            <input name="intro_title" value="{{ old('intro_title', data_get($page, 'intro.title')) }}" placeholder="Intro heading" class="w-full border px-3 py-2 rounded">
            <textarea name="intro_body" rows="4" placeholder="Intro description" class="w-full border px-3 py-2 rounded">{{ old('intro_body', data_get($page, 'intro.body')) }}</textarea>
        </div>
    </details>

    @if($isRailings)
    <details class="bg-white rounded shadow p-4" open>
        <summary class="font-medium cursor-pointer">3. Railing Categories (gallery cards)</summary>
        <div class="mt-4 space-y-3">
            <input name="section_title" value="{{ old('section_title', data_get($page, 'categories.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <textarea name="section_subtitle" rows="2" placeholder="Section subheading" class="w-full border px-3 py-2 rounded">{{ old('section_subtitle', data_get($page, 'categories.subtitle')) }}</textarea>
            <div id="cards-list" class="space-y-4">
                @foreach(old('cards', data_get($page, 'categories.items', [])) as $i => $item)
                @include('admin.independent-pages.partials.card-row', ['prefix' => 'cards', 'index' => $i, 'item' => $item, 'titleKey' => 'title', 'textKey' => 'text', 'preview' => $preview, 'showCta' => true])
                @endforeach
            </div>
            <button type="button" class="text-sm border px-3 py-1.5 rounded" data-add-row="cards" data-title-key="title" data-text-key="text" data-show-cta="1">+ Add category card</button>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">4. Staircase &amp; Layout Shapes</summary>
        <div class="mt-4 space-y-3">
            <input name="layouts_title" value="{{ old('layouts_title', data_get($page, 'layouts.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <textarea name="layouts_subtitle" rows="2" placeholder="Section subheading" class="w-full border px-3 py-2 rounded">{{ old('layouts_subtitle', data_get($page, 'layouts.subtitle')) }}</textarea>
            <div id="layouts-list" class="space-y-3">
                @foreach(old('layouts', data_get($page, 'layouts.items', [])) as $i => $item)
                <div class="border rounded p-3 bg-gray-50 space-y-2" data-row>
                    <input name="layouts[{{ $i }}][title]" value="{{ $item['title'] ?? '' }}" placeholder="Shape title" class="w-full border px-3 py-2 rounded">
                    <textarea name="layouts[{{ $i }}][text]" rows="2" placeholder="Shape description" class="w-full border px-3 py-2 rounded">{{ $item['text'] ?? '' }}</textarea>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="layouts[{{ $i }}][active]" value="1" @checked(($item['active'] ?? true) !== false)> Active</label>
                    <button type="button" class="text-red-600 text-sm" data-remove-row>Remove</button>
                </div>
                @endforeach
            </div>
            <button type="button" class="text-sm border px-3 py-1.5 rounded" data-add-layout>+ Add layout</button>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">5. Why Specify Vyomika Atelier</summary>
        <div class="mt-4 space-y-3">
            <input name="why_title" value="{{ old('why_title', data_get($page, 'why.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <textarea name="why_points" rows="5" placeholder="Benefit points (one per line)" class="w-full border px-3 py-2 rounded">{{ old('why_points', $lines(data_get($page, 'why.items'))) }}</textarea>
            @php $whyImage = data_get($page, 'why.image'); @endphp
            @if($whyImage)<img src="{{ $preview($whyImage) }}" alt="" class="w-full max-w-sm h-32 object-cover rounded border">@endif
            <input name="why_image_alt" value="{{ old('why_image_alt', data_get($page, 'why.image_alt')) }}" placeholder="Supporting image alt" class="w-full border px-3 py-2 rounded">
            <input name="why_image" value="{{ old('why_image', $whyImage) }}" placeholder="Supporting image URL" class="w-full border px-3 py-2 rounded">
            <input type="file" name="why_image_file" accept="image/jpeg,image/png,image/webp">
            @if($whyImage)
            <label class="inline-flex items-center gap-2 text-sm text-red-700"><input type="checkbox" name="why_image_remove" value="1"> Remove supporting image</label>
            @endif
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">6. Quotation CTA (form reused)</summary>
        <div class="mt-4 space-y-3">
            <p class="text-xs text-gray-500">The quotation form itself is the existing protected Railings Quote form — only the surrounding copy is edited here.</p>
            <input name="quote_title" value="{{ old('quote_title', data_get($page, 'quote.title')) }}" placeholder="CTA heading" class="w-full border px-3 py-2 rounded">
            <textarea name="quote_body" rows="3" placeholder="CTA description" class="w-full border px-3 py-2 rounded">{{ old('quote_body', data_get($page, 'quote.body')) }}</textarea>
            <textarea name="quote_bullets" rows="3" placeholder="Bullets (one per line)" class="w-full border px-3 py-2 rounded">{{ old('quote_bullets', $lines(data_get($page, 'quote.bullets'))) }}</textarea>
        </div>
    </details>
    @endif

    @if($isCorten)
    <details class="bg-white rounded shadow p-4" open>
        <summary class="font-medium cursor-pointer">3. Applications (gallery cards)</summary>
        <div class="mt-4 space-y-3">
            <input name="section_title" value="{{ old('section_title', data_get($page, 'applications.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <div id="apps-list" class="space-y-4">
                @foreach(old('apps', data_get($page, 'applications.items', [])) as $i => $item)
                @include('admin.independent-pages.partials.card-row', ['prefix' => 'apps', 'index' => $i, 'item' => $item, 'titleKey' => 'name', 'textKey' => 'text', 'preview' => $preview, 'showCta' => false])
                @endforeach
            </div>
            <button type="button" class="text-sm border px-3 py-1.5 rounded" data-add-row="apps" data-title-key="name" data-text-key="text">+ Add application</button>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">4. Why Choose Corten Steel</summary>
        <div class="mt-4 space-y-3">
            <input name="why_title" value="{{ old('why_title', data_get($page, 'why.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <textarea name="why_points" rows="5" placeholder="Benefit points (one per line)" class="w-full border px-3 py-2 rounded">{{ old('why_points', $lines(data_get($page, 'why.points'))) }}</textarea>
            @php $whyImage = data_get($page, 'why.image'); @endphp
            @if($whyImage)<img src="{{ $preview($whyImage) }}" alt="" class="w-full max-w-sm h-32 object-cover rounded border">@endif
            <input name="why_image_alt" value="{{ old('why_image_alt', data_get($page, 'why.image_alt')) }}" placeholder="Supporting image alt" class="w-full border px-3 py-2 rounded">
            <input name="why_image" value="{{ old('why_image', $whyImage) }}" placeholder="Supporting image URL" class="w-full border px-3 py-2 rounded">
            <input type="file" name="why_image_file" accept="image/jpeg,image/png,image/webp">
            @if($whyImage)
            <label class="inline-flex items-center gap-2 text-sm text-red-700"><input type="checkbox" name="why_image_remove" value="1"> Remove supporting image</label>
            @endif
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">5. Finish That Changes with Time</summary>
        <div class="mt-4 space-y-3">
            <input name="finish_title" value="{{ old('finish_title', data_get($page, 'finish_evolution.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <textarea name="finish_note" rows="2" placeholder="Section note" class="w-full border px-3 py-2 rounded">{{ old('finish_note', data_get($page, 'finish_evolution.note')) }}</textarea>
            <div id="stages-list" class="space-y-4">
                @foreach(old('stages', data_get($page, 'finish_evolution.stages', [])) as $i => $item)
                @include('admin.independent-pages.partials.card-row', ['prefix' => 'stages', 'index' => $i, 'item' => $item, 'titleKey' => 'label', 'textKey' => 'text', 'preview' => $preview, 'showCta' => false])
                @endforeach
            </div>
            <button type="button" class="text-sm border px-3 py-1.5 rounded" data-add-row="stages" data-title-key="label" data-text-key="text">+ Add stage</button>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">6. Made for Your Project (process)</summary>
        <div class="mt-4 space-y-3">
            <input name="process_title" value="{{ old('process_title', data_get($page, 'process.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <textarea name="process_steps" rows="6" placeholder="Steps (one per line)" class="w-full border px-3 py-2 rounded">{{ old('process_steps', $lines(data_get($page, 'process.steps'))) }}</textarea>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">7. Featured Projects</summary>
        <div class="mt-4 space-y-3">
            <input name="projects_title" value="{{ old('projects_title', data_get($page, 'featured_projects.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <textarea name="projects_categories" rows="2" placeholder="Category labels (one per line)" class="w-full border px-3 py-2 rounded">{{ old('projects_categories', $lines(data_get($page, 'featured_projects.categories'))) }}</textarea>
            <div id="projects-list" class="space-y-4">
                @foreach(old('projects', data_get($page, 'featured_projects.items', [])) as $i => $item)
                <div class="border rounded p-3 bg-gray-50 space-y-2" data-row>
                    @if(!empty($item['image']))<img src="{{ $preview($item['image']) }}" alt="" class="w-full max-w-xs h-28 object-cover rounded border">@endif
                    <input name="projects[{{ $i }}][title]" value="{{ $item['title'] ?? '' }}" placeholder="Project title" class="w-full border px-3 py-2 rounded">
                    <div class="grid md:grid-cols-2 gap-2">
                        <input name="projects[{{ $i }}][category]" value="{{ $item['category'] ?? '' }}" placeholder="Type / category" class="w-full border px-3 py-2 rounded">
                        <input name="projects[{{ $i }}][location]" value="{{ $item['location'] ?? '' }}" placeholder="Location" class="w-full border px-3 py-2 rounded">
                    </div>
                    <input name="projects[{{ $i }}][slug]" value="{{ $item['slug'] ?? '' }}" placeholder="Project slug (optional — links to /projects/{slug})" class="w-full border px-3 py-2 rounded">
                    <input name="projects[{{ $i }}][image_alt]" value="{{ $item['image_alt'] ?? '' }}" placeholder="Image alt" class="w-full border px-3 py-2 rounded">
                    <input name="projects[{{ $i }}][image]" value="{{ $item['image'] ?? '' }}" placeholder="Image URL" class="w-full border px-3 py-2 rounded">
                    <input type="file" name="projects[{{ $i }}][image_file]" accept="image/jpeg,image/png,image/webp">
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="projects[{{ $i }}][active]" value="1" @checked(($item['active'] ?? true) !== false)> Active</label>
                    <button type="button" class="text-red-600 text-sm" data-remove-row>Remove</button>
                </div>
                @endforeach
            </div>
            <button type="button" class="text-sm border px-3 py-1.5 rounded" data-add-project>+ Add project card</button>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">8. Material &amp; Fabrication</summary>
        <div class="mt-4 space-y-3">
            <input name="technical_title" value="{{ old('technical_title', data_get($page, 'technical.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <textarea name="technical_options" rows="6" placeholder="Options (one per line)" class="w-full border px-3 py-2 rounded">{{ old('technical_options', $lines(data_get($page, 'technical.options'))) }}</textarea>
            @php $techImage = data_get($page, 'technical.image'); @endphp
            @if($techImage)<img src="{{ $preview($techImage) }}" alt="" class="w-full max-w-sm h-32 object-cover rounded border">@endif
            <input name="technical_image_alt" value="{{ old('technical_image_alt', data_get($page, 'technical.image_alt')) }}" placeholder="Supporting image alt" class="w-full border px-3 py-2 rounded">
            <input name="technical_image" value="{{ old('technical_image', $techImage) }}" placeholder="Supporting image URL" class="w-full border px-3 py-2 rounded">
            <input type="file" name="technical_image_file" accept="image/jpeg,image/png,image/webp">
            @if($techImage)
            <label class="inline-flex items-center gap-2 text-sm text-red-700"><input type="checkbox" name="technical_image_remove" value="1"> Remove supporting image</label>
            @endif
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">9. Planning for Corten Steel</summary>
        <div class="mt-4 space-y-3">
            <input name="considerations_title" value="{{ old('considerations_title', data_get($page, 'considerations.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <textarea name="considerations_points" rows="6" placeholder="Planning notes (one per line)" class="w-full border px-3 py-2 rounded">{{ old('considerations_points', $lines(data_get($page, 'considerations.points'))) }}</textarea>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">10. FAQ</summary>
        <div class="mt-4 space-y-3">
            <input name="faq_title" value="{{ old('faq_title', data_get($page, 'faq.title')) }}" placeholder="Section heading" class="w-full border px-3 py-2 rounded">
            <div id="faqs-list" class="space-y-3">
                @foreach(old('faqs', data_get($page, 'faq.items', [])) as $i => $item)
                <div class="border rounded p-3 bg-gray-50 space-y-2" data-row>
                    <input name="faqs[{{ $i }}][q]" value="{{ $item['q'] ?? '' }}" placeholder="Question" class="w-full border px-3 py-2 rounded">
                    <textarea name="faqs[{{ $i }}][a]" rows="3" placeholder="Answer" class="w-full border px-3 py-2 rounded">{{ $item['a'] ?? '' }}</textarea>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="faqs[{{ $i }}][active]" value="1" @checked(($item['active'] ?? true) !== false)> Active</label>
                    <button type="button" class="text-red-600 text-sm" data-remove-row>Remove</button>
                </div>
                @endforeach
            </div>
            <button type="button" class="text-sm border px-3 py-1.5 rounded" data-add-faq>+ Add FAQ</button>
        </div>
    </details>

    <details class="bg-white rounded shadow p-4">
        <summary class="font-medium cursor-pointer">11. Final CTA &amp; Enquiry</summary>
        <div class="mt-4 space-y-3">
            <p class="text-xs text-gray-500">Enquiry form reuses the existing protected Lead form.</p>
            <input name="cta_title" value="{{ old('cta_title', data_get($page, 'cta.title')) }}" placeholder="CTA heading" class="w-full border px-3 py-2 rounded">
            <textarea name="cta_body" rows="3" placeholder="CTA description" class="w-full border px-3 py-2 rounded">{{ old('cta_body', data_get($page, 'cta.body')) }}</textarea>
            <div class="grid md:grid-cols-2 gap-3">
                <input name="cta_form_label" value="{{ old('cta_form_label', data_get($page, 'cta.form_label')) }}" placeholder="Enquiry form eyebrow" class="w-full border px-3 py-2 rounded">
                <input name="cta_form_title" value="{{ old('cta_form_title', data_get($page, 'cta.form_title')) }}" placeholder="Enquiry form title" class="w-full border px-3 py-2 rounded">
                <input name="cta_secondary_label" value="{{ old('cta_secondary_label', data_get($page, 'cta.secondary.label')) }}" placeholder="Secondary button label" class="w-full border px-3 py-2 rounded">
                <input name="cta_secondary_href" value="{{ old('cta_secondary_href', data_get($page, 'cta.secondary.href')) }}" placeholder="Secondary button URL" class="w-full border px-3 py-2 rounded">
            </div>
        </div>
    </details>
    @endif

    <div class="sticky bottom-0 bg-gray-50 border-t py-3 flex gap-3">
        <button class="bg-gray-900 text-white px-5 py-2 rounded text-sm">Save page</button>
        <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="border px-4 py-2 rounded text-sm">Preview</a>
    </div>
</form>

<template id="tpl-card-row">
    <div class="border rounded p-3 bg-gray-50 space-y-2" data-row>
        <input data-field="title" placeholder="Title" class="w-full border px-3 py-2 rounded">
        <textarea data-field="text" rows="2" placeholder="Description" class="w-full border px-3 py-2 rounded"></textarea>
        <input data-field="image_alt" placeholder="Image alt text" class="w-full border px-3 py-2 rounded">
        <input data-field="image" placeholder="Image URL" class="w-full border px-3 py-2 rounded">
        <input type="file" data-field="image_file" accept="image/jpeg,image/png,image/webp">
        <div class="grid md:grid-cols-2 gap-2" data-cta-fields>
            <input data-field="cta_label" placeholder="CTA label (optional)" class="w-full border px-3 py-2 rounded">
            <input data-field="cta_href" placeholder="CTA URL / #anchor (optional)" class="w-full border px-3 py-2 rounded">
        </div>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" data-field="active" value="1" checked> Active</label>
        <button type="button" class="text-red-600 text-sm" data-remove-row>Remove</button>
    </div>
</template>

<script>
(function () {
    function reindex(container, prefix, titleKey, textKey) {
        container.querySelectorAll('[data-row]').forEach(function (row, i) {
            row.querySelectorAll('[data-field]').forEach(function (el) {
                var field = el.getAttribute('data-field');
                if (field === 'title') el.name = prefix + '[' + i + '][' + titleKey + ']';
                else if (field === 'text') {
                    if (!textKey) { el.remove(); return; }
                    el.name = prefix + '[' + i + '][' + textKey + ']';
                }
                else if (field === 'image_file') el.name = prefix + '[' + i + '][image_file]';
                else el.name = prefix + '[' + i + '][' + field + ']';
            });
        });
    }

    document.querySelectorAll('[data-add-row]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var prefix = btn.getAttribute('data-add-row');
            var titleKey = btn.getAttribute('data-title-key') || 'title';
            var textKey = btn.getAttribute('data-text-key');
            var showCta = btn.getAttribute('data-show-cta') === '1';
            var list = document.getElementById(prefix + '-list');
            var tpl = document.getElementById('tpl-card-row');
            if (!list || !tpl) return;
            var node = tpl.content.cloneNode(true);
            if (!textKey) {
                var ta = node.querySelector('[data-field="text"]');
                if (ta) ta.remove();
            }
            if (!showCta) {
                var cta = node.querySelector('[data-cta-fields]');
                if (cta) cta.remove();
            }
            list.appendChild(node);
            reindex(list, prefix, titleKey, textKey);
        });
    });

    document.querySelector('[data-add-layout]')?.addEventListener('click', function () {
        var list = document.getElementById('layouts-list');
        var i = list.querySelectorAll('[data-row]').length;
        var div = document.createElement('div');
        div.className = 'border rounded p-3 bg-gray-50 space-y-2';
        div.setAttribute('data-row', '');
        div.innerHTML = '<input name="layouts['+i+'][title]" placeholder="Shape title" class="w-full border px-3 py-2 rounded">'
            + '<textarea name="layouts['+i+'][text]" rows="2" placeholder="Shape description" class="w-full border px-3 py-2 rounded"></textarea>'
            + '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="layouts['+i+'][active]" value="1" checked> Active</label>'
            + '<button type="button" class="text-red-600 text-sm" data-remove-row>Remove</button>';
        list.appendChild(div);
    });

    document.querySelector('[data-add-faq]')?.addEventListener('click', function () {
        var list = document.getElementById('faqs-list');
        var i = list.querySelectorAll('[data-row]').length;
        var div = document.createElement('div');
        div.className = 'border rounded p-3 bg-gray-50 space-y-2';
        div.setAttribute('data-row', '');
        div.innerHTML = '<input name="faqs['+i+'][q]" placeholder="Question" class="w-full border px-3 py-2 rounded">'
            + '<textarea name="faqs['+i+'][a]" rows="3" placeholder="Answer" class="w-full border px-3 py-2 rounded"></textarea>'
            + '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="faqs['+i+'][active]" value="1" checked> Active</label>'
            + '<button type="button" class="text-red-600 text-sm" data-remove-row>Remove</button>';
        list.appendChild(div);
    });

    document.querySelector('[data-add-project]')?.addEventListener('click', function () {
        var list = document.getElementById('projects-list');
        var i = list.querySelectorAll('[data-row]').length;
        var div = document.createElement('div');
        div.className = 'border rounded p-3 bg-gray-50 space-y-2';
        div.setAttribute('data-row', '');
        div.innerHTML = '<input name="projects['+i+'][title]" placeholder="Project title" class="w-full border px-3 py-2 rounded">'
            + '<div class="grid md:grid-cols-2 gap-2"><input name="projects['+i+'][category]" placeholder="Type" class="w-full border px-3 py-2 rounded"><input name="projects['+i+'][location]" placeholder="Location" class="w-full border px-3 py-2 rounded"></div>'
            + '<input name="projects['+i+'][slug]" placeholder="Project slug (optional)" class="w-full border px-3 py-2 rounded">'
            + '<input name="projects['+i+'][image_alt]" placeholder="Image alt" class="w-full border px-3 py-2 rounded">'
            + '<input name="projects['+i+'][image]" placeholder="Image URL" class="w-full border px-3 py-2 rounded">'
            + '<input type="file" name="projects['+i+'][image_file]" accept="image/jpeg,image/png,image/webp">'
            + '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="projects['+i+'][active]" value="1" checked> Active</label>'
            + '<button type="button" class="text-red-600 text-sm" data-remove-row>Remove</button>';
        list.appendChild(div);
    });

    document.addEventListener('click', function (e) {
        if (e.target && e.target.matches('[data-remove-row]')) {
            if (!confirm('Remove this item?')) return;
            var row = e.target.closest('[data-row]');
            if (!row) return;
            var list = row.parentElement;
            row.remove();
            if (list && list.id) {
                var prefix = list.id.replace(/-list$/, '');
                var addBtn = document.querySelector('[data-add-row="' + prefix + '"]');
                if (addBtn) {
                    reindex(list, prefix, addBtn.getAttribute('data-title-key') || 'title', addBtn.getAttribute('data-text-key'));
                } else if (prefix === 'layouts') {
                    list.querySelectorAll('[data-row]').forEach(function (item, i) {
                        var title = item.querySelector('[name^="layouts["][name$="[title]"]');
                        var text = item.querySelector('[name^="layouts["][name$="[text]"]');
                        var active = item.querySelector('[name^="layouts["][name$="[active]"]');
                        if (title) title.name = 'layouts[' + i + '][title]';
                        if (text) text.name = 'layouts[' + i + '][text]';
                        if (active) active.name = 'layouts[' + i + '][active]';
                    });
                } else if (prefix === 'faqs') {
                    list.querySelectorAll('[data-row]').forEach(function (item, i) {
                        var q = item.querySelector('[name^="faqs["][name$="[q]"]');
                        var a = item.querySelector('[name^="faqs["][name$="[a]"]');
                        var active = item.querySelector('[name^="faqs["][name$="[active]"]');
                        if (q) q.name = 'faqs[' + i + '][q]';
                        if (a) a.name = 'faqs[' + i + '][a]';
                        if (active) active.name = 'faqs[' + i + '][active]';
                    });
                } else if (prefix === 'projects') {
                    list.querySelectorAll('[data-row]').forEach(function (item, i) {
                        item.querySelectorAll('input, textarea').forEach(function (field) {
                            var match = field.name.match(/^projects\[\d+]\[(.+)]$/);
                            if (match) field.name = 'projects[' + i + '][' + match[1] + ']';
                        });
                    });
                }
            }
        }
    });
})();
</script>
@endsection
