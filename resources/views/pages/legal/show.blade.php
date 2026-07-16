<x-legal-page-layout
    :title="$page['title']"
    :meta-title="$page['meta_title'] ?? null"
    :meta-description="$page['meta_description'] ?? null"
    :last-updated="$lastUpdated"
    :sections="$sections"
    :business="$business"
    :breadcrumbs="[
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Legal', 'url' => route('legal.privacy')],
        ['label' => $page['title']],
    ]"
/>
