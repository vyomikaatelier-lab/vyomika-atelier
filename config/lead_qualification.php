<?php

return [
  'ip_hash_salt' => env('LEAD_IP_HASH_SALT'),

  'duplicate_lookback_hours' => 24,

  'enquiry_types' => [
    'shop_product' => 'Shop Product',
    'studio_project' => 'Studio Project',
    'railings_quote' => 'Railings Quote',
    'professional_b2b' => 'Professional / B2B',
    'dealer' => 'Dealer Application',
    'catalogue' => 'Catalogue Request',
    'general' => 'General Enquiry',
    'vendor_marketing' => 'Vendor / Marketing',
  ],

  'type_to_enquiry' => [
    'inquiry' => 'shop_product',
    'service_inquiry' => 'shop_product',
    'order_now' => 'shop_product',
    'custom_order' => 'studio_project',
    'contact' => 'general',
    'professional_application' => 'professional_b2b',
    'railing_quotation' => 'railings_quote',
    'dealer_application' => 'dealer',
    'catalogue_request' => 'catalogue',
    'vendor_proposal' => 'vendor_marketing',
  ],

  'workflow_statuses' => [
    'new',
    'unverified',
    'verified',
    'qualified',
    'measurement_required',
    'quotation_in_progress',
    'quotation_sent',
    'follow_up',
    'negotiation',
    'won',
    'lost',
    'on_hold',
    'duplicate',
    'marketing_vendor',
    'spam_suspected',
    'blocked',
    // legacy aliases kept readable in admin
    'contacted',
    'quoted',
    'converted',
    'closed',
    'under_review',
    'approved',
    'rejected',
    'more_info_required',
  ],

  'legacy_status_map' => [
    'contacted' => 'follow_up',
    'quoted' => 'quotation_sent',
    'converted' => 'won',
    'closed' => 'lost',
  ],

  'priorities' => ['hot', 'high', 'medium', 'low'],

  'score_bands' => [
    'hot' => 70,
    'qualified' => 40,
    'needs_verification' => 20,
    'low_priority' => 0,
    'spam_review' => PHP_INT_MIN,
  ],

  'scoring' => [
    'positive' => [
      'whatsapp_verified' => 20,
      'valid_email' => 5,
      'project_location' => 10,
      'product_selected' => 10,
      'measurements' => 15,
      'budget' => 10,
      'timeline' => 10,
      'reference_upload' => 15,
      'meaningful_description' => 10,
      'architect_details' => 10,
      'active_project' => 15,
    ],
    'negative' => [
      'duplicate' => 20,
      'disposable' => 30,
      'multiple_urls' => 25,
      'marketing_pitch' => 35,
      'too_fast' => 30,
      'repeated_ip' => 20,
      'honeypot' => 100,
      'invalid_turnstile' => 100,
    ],
  ],

  'catalogue' => [
    'download_ttl_hours' => 72,
    'catalogue_path' => 'catalogue/vyomika-atelier-catalogue.pdf',
  ],

  'acknowledgements' => [
    'genuine' => 'Thank you! We received your enquiry and will contact you shortly.',
    'incomplete' => 'Thank you! We received your details. Our team may reach out for more information.',
    'vendor' => 'Thank you. Your proposal was received and will be reviewed by our partnerships team if relevant.',
    'catalogue' => 'Thank you! Your catalogue download link will be sent shortly.',
    'duplicate' => 'Thank you! We already have your recent enquiry on file and will be in touch.',
  ],
];
