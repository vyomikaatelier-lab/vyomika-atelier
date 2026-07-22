<?php

/**
 * Central legal & business configuration — edit placeholders here.
 * Brand display name may differ from registered legal entity.
 */
return [
    'last_updated' => '15 July 2026',

    'business' => [
        'brand_name' => 'Vyomika Atelier LLP',
        'legal_name' => 'VYOMIKA SALES',
        'country' => 'India',
        'business_type' => 'Custom-made premium interior and architectural metal products (made-to-order)',
        'email' => 'namaste@vyomikaatelier.com',
        'phone' => '+91 9205850254',
        'address' => '467-483/1, Burgees Park, Dilshad Garden Ind. Area, G.T. Road, Shahdra, Delhi, India',
        'gstin' => '07ASEPM3287D2ZQ',
        'pan' => 'ASEPM3287D',
        'state_code' => '07',
        'grievance_officer_name' => 'Vyomika Atelier Customer Care',
        'grievance_officer_email' => 'namaste@vyomikaatelier.com',
        'grievance_officer_phone' => '+91 9205850254',
        'registration_note' => 'Registered firm in Delhi, India · State: Delhi (07)',
    ],

    'footer_links' => [
        ['label' => 'Privacy Policy', 'route' => 'legal.privacy'],
        ['label' => 'Terms & Conditions', 'route' => 'legal.terms'],
        ['label' => 'Shipping & Delivery', 'route' => 'legal.shipping'],
        ['label' => 'Cancellation & Refund', 'route' => 'legal.cancellation'],
        ['label' => 'Warranty & Returns', 'route' => 'legal.warranty'],
        ['label' => 'Contact & Grievance', 'route' => 'legal.grievance'],
    ],

    'pages' => [
        'privacy' => [
            'title' => 'Privacy Policy',
            'meta_title' => 'Privacy Policy — Vyomika Atelier LLP',
            'meta_description' => 'How Vyomika Atelier LLP collects, uses, and protects your personal information on enquiry forms, orders, and analytics.',
            'sections' => [
                [
                    'heading' => 'Introduction',
                    'paragraphs' => [
                        '{{legal_name}} ("we", "us", "our"), trading as {{brand_name}}, respects your privacy. This policy explains how we collect, use, store, and protect personal information when you visit our website, submit enquiries, request quotes, or place orders for custom metalwork.',
                        'We do not sell your personal data to third parties.',
                    ],
                ],
                [
                    'heading' => 'Information We Collect',
                    'paragraphs' => [
                        'We may collect: name, email address, phone number, project location, shipping or site address, product specifications, dimensions, finish preferences, messages submitted via contact or enquiry forms, calculator inputs, and order-related details.',
                        'We may also collect technical data such as IP address, browser type, device information, and usage data through cookies and analytics tools to improve our website experience.',
                    ],
                ],
                [
                    'heading' => 'How We Use Your Information',
                    'paragraphs' => [
                        'To respond to enquiries and provide quotations; process and fulfil custom orders; communicate about design approval, production, and delivery; improve our website and services; comply with legal obligations; and send service-related updates you have requested.',
                    ],
                ],
                [
                    'heading' => 'Cookies & Analytics',
                    'paragraphs' => [
                        'Our website may use cookies and similar technologies to remember preferences and understand how visitors use our pages. You can control cookies through your browser settings. Analytics data is used in aggregate to improve performance and content.',
                    ],
                ],
                [
                    'heading' => 'Data Sharing',
                    'paragraphs' => [
                        'We may share information with trusted service providers (payment processors, logistics partners, fabrication subcontractors) only as needed to fulfil your order. We require partners to handle data responsibly. We do not sell personal information to marketers or data brokers.',
                    ],
                ],
                [
                    'heading' => 'Data Retention & Security',
                    'paragraphs' => [
                        'We retain information for as long as needed to fulfil orders, resolve disputes, and meet legal requirements. We implement reasonable technical and organisational measures to protect your data; however, no online transmission is completely secure.',
                    ],
                ],
                [
                    'heading' => 'Your Rights',
                    'paragraphs' => [
                        'You may request access, correction, or deletion of your personal data, subject to applicable law and ongoing order obligations. Contact us using the details below.',
                    ],
                ],
                [
                    'heading' => 'Contact',
                    'paragraphs' => [
                        'Privacy enquiries: {{email}} · {{phone}} · {{address}}',
                    ],
                ],
            ],
        ],

        'terms' => [
            'title' => 'Terms & Conditions',
            'meta_title' => 'Terms & Conditions — Vyomika Atelier LLP',
            'meta_description' => 'Website usage terms, intellectual property, and ordering conditions for Vyomika Atelier LLP custom metal products.',
            'sections' => [
                [
                    'heading' => 'Agreement',
                    'paragraphs' => [
                        'By accessing {{brand_name}} (operated by {{legal_name}}) and placing enquiries or orders, you agree to these Terms & Conditions. If you do not agree, please do not use this website.',
                    ],
                ],
                [
                    'heading' => 'Products & Custom Orders',
                    'paragraphs' => [
                        '{{brand_name}} specialises in {{business_type}}. All items are made to order unless explicitly stated otherwise. Specifications, finishes, dimensions, and pricing are confirmed in writing before production begins.',
                        'Images and renderings on this website are representative. Actual colours, textures, PVD finishes, and metal tones may vary slightly due to lighting, screen settings, batch variation, and the handmade nature of fabrication.',
                    ],
                ],
                [
                    'heading' => 'Intellectual Property',
                    'paragraphs' => [
                        'All website content — including designs, photographs, text, logos, and layouts — is owned by or licensed to {{legal_name}} and protected by applicable intellectual property laws. You may not copy, reproduce, or distribute our content without written permission.',
                    ],
                ],
                [
                    'heading' => 'Website Use',
                    'paragraphs' => [
                        'You agree not to misuse the website, attempt unauthorised access, submit false information, or use our content for commercial purposes without consent. We may update these terms at any time; continued use constitutes acceptance of the revised terms.',
                    ],
                ],
                [
                    'heading' => 'Limitation of Liability',
                    'paragraphs' => [
                        'To the fullest extent permitted by law, {{legal_name}} is not liable for indirect, incidental, or consequential damages arising from website use or custom product orders, except where liability cannot be excluded under Indian law.',
                    ],
                ],
                [
                    'heading' => 'Governing Law',
                    'paragraphs' => [
                        'These terms are governed by the laws of {{country}}. Disputes shall be subject to the exclusive jurisdiction of courts in Delhi, unless otherwise required by law.',
                    ],
                ],
            ],
        ],

        'shipping' => [
            'title' => 'Shipping & Delivery Policy',
            'meta_title' => 'Shipping & Delivery Policy — Vyomika Atelier LLP',
            'meta_description' => 'Delivery timelines, logistics, and installation notes for custom Vyomika Atelier LLP metal products across India.',
            'sections' => [
                [
                    'heading' => 'Made-to-Order Production',
                    'paragraphs' => [
                        'Every {{brand_name}} product is fabricated after design approval. Delivery timelines depend on design complexity, finish selection, approval cycles, and current studio workload — typically **3–4 weeks** from order confirmation, unless otherwise quoted.',
                    ],
                ],
                [
                    'heading' => 'Delivery Location',
                    'paragraphs' => [
                        'We deliver across major cities in {{country}}. Project location, site access, and local regulations may affect scheduling. Remote or difficult-access sites may require additional coordination.',
                    ],
                ],
                [
                    'heading' => 'Transportation & Installation',
                    'paragraphs' => [
                        'Standard quotes include secure packaging and dispatch to your delivery address. **Transportation beyond the quoted zone, crane hire, on-site installation, and civil works are charged separately** unless explicitly included in your written quotation.',
                    ],
                ],
                [
                    'heading' => 'Inspection on Delivery',
                    'paragraphs' => [
                        'Please inspect items upon receipt. Report visible transit damage within **48 hours** with photographs so we can arrange assessment and remedy where applicable.',
                    ],
                ],
                [
                    'heading' => 'Delays',
                    'paragraphs' => [
                        'We are not responsible for delays caused by force majeure, customs, carrier disruptions, or client-side approval delays. We will communicate proactively if production or dispatch timelines change.',
                    ],
                ],
            ],
        ],

        'cancellation' => [
            'title' => 'Cancellation & Refund Policy',
            'meta_title' => 'Cancellation & Refund Policy — Vyomika Atelier LLP',
            'meta_description' => 'Cancellation rules and refund terms for custom-made Vyomika Atelier LLP orders.',
            'sections' => [
                [
                    'heading' => 'Custom / Made-to-Order Products',
                    'paragraphs' => [
                        'All {{brand_name}} products are made to your specifications. **Orders cannot be cancelled once production has begun** or materials have been procured for your project.',
                    ],
                ],
                [
                    'heading' => 'Advance Payments',
                    'paragraphs' => [
                        'Advance payments confirming your order are **non-refundable once materials are purchased** or fabrication has started. Partial refunds before material procurement may be considered at our discretion, minus administrative costs.',
                    ],
                ],
                [
                    'heading' => 'Design Approval',
                    'paragraphs' => [
                        'By approving final drawings, dimensions, and finishes, you authorise production. Changes after approval may incur additional charges and timeline extensions.',
                    ],
                ],
                [
                    'heading' => 'Refund Processing',
                    'paragraphs' => [
                        'Approved refunds, if any, are processed to the original payment method within **7–14 business days**, subject to bank or payment-gateway timelines.',
                    ],
                ],
                [
                    'heading' => 'Contact',
                    'paragraphs' => [
                        'Cancellation requests: {{email}} · {{phone}}',
                    ],
                ],
            ],
        ],

        'warranty' => [
            'title' => 'Warranty & Returns Policy',
            'meta_title' => 'Warranty & Returns Policy — Vyomika Atelier LLP',
            'meta_description' => 'Warranty coverage and returns policy for custom Vyomika Atelier LLP architectural metalwork.',
            'sections' => [
                [
                    'heading' => 'No Returns on Custom Products',
                    'paragraphs' => [
                        'Because every item is custom-fabricated, **we do not accept returns or exchanges** on made-to-order products unless a verified manufacturing defect is confirmed by our technical team.',
                    ],
                ],
                [
                    'heading' => 'Manufacturing Defects',
                    'paragraphs' => [
                        'If you believe an item has a manufacturing defect, notify us within **7 days of delivery** with clear photographs and a description. We will inspect and, where confirmed, repair, replace, or remedy at no additional cost for the defective component.',
                    ],
                ],
                [
                    'heading' => 'Exclusions',
                    'paragraphs' => [
                        'This policy does not cover: normal wear and tear; scratches or damage from improper cleaning or abrasive materials; site installation errors by third parties; modifications made after delivery; or damage from misuse, accidents, or environmental factors beyond product specification.',
                    ],
                ],
                [
                    'heading' => 'PVD & Metal Care',
                    'paragraphs' => [
                        'PVD finishes require gentle care per our care guidelines. Damage from harsh chemicals, steel wool, or neglect is not covered under warranty.',
                    ],
                ],
                [
                    'heading' => 'Contact',
                    'paragraphs' => [
                        'Warranty claims: {{email}} · {{phone}}',
                    ],
                ],
            ],
        ],

        'grievance' => [
            'title' => 'Contact & Grievance Policy',
            'meta_title' => 'Contact & Grievance Policy — Vyomika Atelier LLP',
            'meta_description' => 'Official contact details and grievance redressal for Vyomika Atelier LLP customers in India.',
            'sections' => [
                [
                    'heading' => 'Business Information',
                    'paragraphs' => [
                        '**Brand:** {{brand_name}} · **Legal entity:** {{legal_name}} · **Country:** {{country}} · **Registration:** {{registration_note}}',
                        '**Address:** {{address}} · **GSTIN:** {{gstin}} · **PAN:** {{pan}} · **Email:** {{email}} · **Phone:** {{phone}}',
                    ],
                ],
                [
                    'heading' => 'Customer Support',
                    'paragraphs' => [
                        'For orders, quotations, and general enquiries, visit our <a href="/contact">Contact page</a> or write to {{email}}. Our studio team responds Monday–Saturday during business hours (IST).',
                    ],
                ],
                [
                    'heading' => 'Grievance Officer',
                    'paragraphs' => [
                        'In accordance with applicable consumer protection and information-technology rules in {{country}}, grievances may be directed to:',
                        '**Name:** {{grievance_officer_name}} · **Email:** {{grievance_officer_email}} · **Phone:** {{grievance_officer_phone}}',
                        'We aim to acknowledge grievances within **48 hours** and resolve them within **15 business days**, subject to complexity.',
                    ],
                ],
                [
                    'heading' => 'Escalation',
                    'paragraphs' => [
                        'If you are unsatisfied with our response, you may escalate to relevant consumer forums or authorities in {{country}} as permitted by law.',
                    ],
                ],
            ],
        ],
    ],
];
