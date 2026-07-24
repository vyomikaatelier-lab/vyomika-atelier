<?php

namespace App\Http\Requests;

use App\Support\ResponsiveHero;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIndependentLandingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'hero_label' => 'nullable|string|max:120',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:2000',
            ...ResponsiveHero::flatValidationRules('hero'),
            'hero_image_alt' => 'nullable|string|max:255',
            'hero_cta_primary_label' => 'nullable|string|max:120',
            'hero_cta_primary_href' => 'nullable|string|max:255',
            'hero_cta_secondary_label' => 'nullable|string|max:120',
            'hero_cta_secondary_href' => 'nullable|string|max:255',
            'hero_highlights' => 'nullable|string|max:2000',
            'intro_title' => 'nullable|string|max:255',
            'intro_body' => 'nullable|string|max:8000',
            'section_title' => 'nullable|string|max:255',
            'section_subtitle' => 'nullable|string|max:2000',
            'layouts_title' => 'nullable|string|max:255',
            'layouts_subtitle' => 'nullable|string|max:2000',
            'why_title' => 'nullable|string|max:255',
            'why_points' => 'nullable|string|max:8000',
            'why_image' => 'nullable|string|max:500',
            'why_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'why_image_remove' => 'nullable|boolean',
            'why_image_alt' => 'nullable|string|max:255',
            'quote_title' => 'nullable|string|max:255',
            'quote_body' => 'nullable|string|max:5000',
            'quote_bullets' => 'nullable|string|max:4000',
            'cta_title' => 'nullable|string|max:255',
            'cta_body' => 'nullable|string|max:5000',
            'cta_form_label' => 'nullable|string|max:120',
            'cta_form_title' => 'nullable|string|max:255',
            'cta_secondary_label' => 'nullable|string|max:120',
            'cta_secondary_href' => 'nullable|string|max:255',
            'finish_title' => 'nullable|string|max:255',
            'finish_note' => 'nullable|string|max:2000',
            'process_title' => 'nullable|string|max:255',
            'process_steps' => 'nullable|string|max:4000',
            'projects_title' => 'nullable|string|max:255',
            'projects_categories' => 'nullable|string|max:1000',
            'technical_title' => 'nullable|string|max:255',
            'technical_options' => 'nullable|string|max:8000',
            'technical_image' => 'nullable|string|max:500',
            'technical_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'technical_image_remove' => 'nullable|boolean',
            'technical_image_alt' => 'nullable|string|max:255',
            'considerations_title' => 'nullable|string|max:255',
            'considerations_points' => 'nullable|string|max:8000',
            'faq_title' => 'nullable|string|max:255',
            'cards' => 'nullable|array',
            'cards.*.title' => 'nullable|string|max:255',
            'cards.*.text' => 'nullable|string|max:2000',
            'cards.*.image' => 'nullable|string|max:500',
            'cards.*.image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'cards.*.image_alt' => 'nullable|string|max:255',
            'cards.*.cta_label' => 'nullable|string|max:120',
            'cards.*.cta_href' => 'nullable|string|max:255',
            'cards.*.active' => 'nullable',
            'layouts' => 'nullable|array',
            'layouts.*.title' => 'nullable|string|max:255',
            'layouts.*.text' => 'nullable|string|max:2000',
            'layouts.*.active' => 'nullable',
            'apps' => 'nullable|array',
            'apps.*.name' => 'nullable|string|max:255',
            'apps.*.text' => 'nullable|string|max:2000',
            'apps.*.image' => 'nullable|string|max:500',
            'apps.*.image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'apps.*.image_alt' => 'nullable|string|max:255',
            'apps.*.active' => 'nullable',
            'stages' => 'nullable|array',
            'stages.*.label' => 'nullable|string|max:255',
            'stages.*.text' => 'nullable|string|max:2000',
            'stages.*.image' => 'nullable|string|max:500',
            'stages.*.image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'stages.*.image_alt' => 'nullable|string|max:255',
            'stages.*.active' => 'nullable',
            'projects' => 'nullable|array',
            'projects.*.title' => 'nullable|string|max:255',
            'projects.*.category' => 'nullable|string|max:255',
            'projects.*.location' => 'nullable|string|max:255',
            'projects.*.slug' => 'nullable|string|max:255',
            'projects.*.image' => 'nullable|string|max:500',
            'projects.*.image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'projects.*.image_alt' => 'nullable|string|max:255',
            'projects.*.active' => 'nullable',
            'faqs' => 'nullable|array',
            'faqs.*.q' => 'nullable|string|max:500',
            'faqs.*.a' => 'nullable|string|max:5000',
            'faqs.*.active' => 'nullable',
        ];
    }
}
