<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * MainSeeder
 *
 * Populates the database with the data that must exist before
 * `pnpm generate` is run on the Nuxt site. Without this seed
 * the static build will receive 404s from the API for each
 * built-in page and fail.
 *
 * Run:  php spark db:seed MainSeeder
 */
class MainSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedPages();

        echo "✓ JNV database seeded successfully.\n";
        echo "  Next step: update admin_password_hash via the admin panel\n";
        echo "  or run: php spark db:seed AdminPasswordSeeder\n";
    }

    // ----------------------------------------------------------------
    // Settings
    // ----------------------------------------------------------------

    private function seedSettings(): void
    {
        $settings = [
            // Default password is "changeme" — MUST be changed after first deploy
            'admin_password_hash' => password_hash('changeme', PASSWORD_BCRYPT),

            'phone_mobile'      => '084 471 0764',
            'phone_office'      => '087 265 5776',
            'email'             => 'info@jnv.co.za',
            'whatsapp_number'   => '27844710764',
            'whatsapp_display'  => '+27 84 471 0764',
            'address_physical'  => "Office No. 101, Neo Plaza\n9105 Pilane Street\nZone 1, Ga-Rankuwa, 0208",
            'address_postal'    => "Postnet Suite 120\nPrivate Bag X 201\nHatfield, 0028",
            'established_year'  => '2013',
            'accreditations'    => json_encode(['AgriSETA', 'QCTO']),
        ];

        foreach ($settings as $key => $value) {
            $this->db->table('settings')->upsert([
                'key'   => $key,
                'value' => $value,
            ]);
        }

        echo "  Settings seeded.\n";
    }

    // ----------------------------------------------------------------
    // Pages — full content matching jnv-site/content/pages/*.json
    // ----------------------------------------------------------------

    private function seedPages(): void
    {
        foreach ($this->builtinPages() as $slug => $data) {
            $this->db->table('pages')->upsert([
                'slug'       => $slug,
                'data'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            echo "  Page '{$slug}' upserted.\n";
        }
    }

    private function builtinPages(): array
    {
        return [

            // ── Home ──────────────────────────────────────────────────────
            'home' => [
                'seoTitle'       => 'JNV Training and Development — AgriSETA Accredited Training South Africa',
                'seoDescription' => 'JNV delivers accredited training, SDF and compliance support, and farm development services across South Africa. AgriSETA and QCTO accredited.',
                'content' => [
                    'hero' => [
                        'tagline'  => 'South African training & development partner',
                        'heading'  => 'Building skills, developing farms, delivering impact.',
                        'body'     => 'JNV delivers accredited training, SDF and compliance support, farm development, and agricultural project implementation across South Africa.',
                        'pills'    => ['Training and Development', 'SDF and Compliance', 'Farm Development'],
                        'ctaLabel' => 'Start Your Project',
                        'imageUrl' => '/images/hero.jpg',
                        'card'     => [
                            'title' => 'What we help you do',
                            'items' => [
                                ['title' => 'Train your people',          'desc' => 'Deliver accredited programmes, workplace learning, and practical assessments.'],
                                ['title' => 'Stay compliant',             'desc' => 'Get support with WSPs, ATRs, levy claims, quality assurance, and accreditation.'],
                                ['title' => 'Develop agricultural projects', 'desc' => 'Plan, structure, and implement farm development and enterprise support initiatives.'],
                            ],
                        ],
                    ],
                    'mediaSection' => [
                        'eyebrow'    => 'What We Do',
                        'heading'    => 'Training, compliance, and development — all in one team.',
                        'body'       => 'From accredited learnerships and workplace training to WSP submissions and farm development projects, JNV provides the full range of skills development services that South African employers and funders need.',
                        'imageUrl'   => '/images/training-section.jpg',
                        'mediaItems' => [
                            'AgriSETA & QCTO accredited delivery',
                            'Learnerships across agriculture, horticulture, and more',
                            'WSP/ATR submissions and skills compliance support',
                            'Farm development from planning to implementation',
                        ],
                    ],
                    'aboutStrip' => [
                        'eyebrow'   => 'About JNV',
                        'heading'   => 'A practical training and development company built for real delivery.',
                        'body'      => 'JNV was established in 2013 as a training and development company focused on skills development, business empowerment, and accredited training solutions, with strong work in agriculture, agribusiness, landscaping, and environmental management.',
                        'body2'     => 'We support SETAs, government departments, employers, communities, cooperatives, and emerging farmers through accredited learning, workplace support, and development projects.',
                        'linkLabel' => 'Learn about our story',
                    ],
                    'services' => [
                        'eyebrow' => 'Core Services',
                        'heading' => 'Everything you need to train, comply, and grow.',
                        'body'    => 'Our services are structured to help organisations build skills, meet compliance requirements, and implement practical development projects that create measurable impact.',
                        'items'   => [
                            [
                                'num'   => '01',
                                'title' => 'Training and Development',
                                'href'  => '/training',
                                'items' => [
                                    'Learnerships and skills programmes',
                                    'Workplace practical training',
                                    'Training material development',
                                    'Facilitation, assessment, and moderation',
                                ],
                            ],
                            [
                                'num'   => '02',
                                'title' => 'SDF and Compliance',
                                'href'  => '/compliance',
                                'items' => [
                                    'WSP and ATR submissions',
                                    'Skills audits and training plans',
                                    'Levy claims and grant support',
                                    'Accreditation and quality assurance',
                                ],
                            ],
                            [
                                'num'   => '03',
                                'title' => 'Farm Development',
                                'href'  => '/farm-development',
                                'items' => [
                                    'Farm planning and feasibility',
                                    'Packhouse development support',
                                    'Crop and livestock systems',
                                    'Enterprise and cooperative support',
                                ],
                            ],
                        ],
                    ],
                    'whyJnv' => [
                        'eyebrow' => 'Why JNV',
                        'heading' => 'Built for employers, funders, development partners, and farmers.',
                        'items'   => [
                            ['title' => 'Accredited delivery',  'desc' => 'Aligned to quality and compliance expectations of AgriSETA and QCTO standards.'],
                            ['title' => 'Field experience',     'desc' => 'Strong grounding in training and project implementation across South African agriculture.'],
                            ['title' => 'Responsive support',   'desc' => 'Practical help from planning to reporting — we stay engaged through the full lifecycle.'],
                            ['title' => 'National reach',       'desc' => 'Support across multiple provinces and client types — from commercial farms to municipalities.'],
                        ],
                    ],
                    'cta' => [
                        'eyebrow'  => 'Get Started',
                        'heading'  => 'Ready to modernise your training or farm development project?',
                        'body'     => 'Talk to us about your training, compliance, or agricultural development needs.',
                        'ctaLabel' => 'Get a Quote',
                        'imageUrl' => '/images/farm.jpg',
                    ],
                ],
            ],

            // ── About ─────────────────────────────────────────────────────
            'about' => [
                'eyebrow'        => 'About JNV',
                'title'          => 'One company, refined for stronger positioning.',
                'body'           => 'JNV Training and Development is the refined brand identity of JNV Landscaping and Training. The same company, with a sharper focus on accredited training, compliance support, and agricultural project implementation.',
                'image'          => '/images/about.jpg',
                'seoTitle'       => 'About JNV Training and Development',
                'seoDescription' => 'JNV Training and Development — established 2013. AgriSETA and QCTO accredited training, compliance, and farm development across South Africa.',
                'content' => [
                    'whoWeAre' => [
                        'eyebrow' => 'Who We Are',
                        'heading' => 'Focused on practical impact.',
                        'body'    => 'JNV was established in 2013 with a focus on skills development, business empowerment, and accredited training solutions, with strong work in agriculture, agribusiness, landscaping, and environmental management.',
                        'body2'   => 'We support SETAs, government departments, employers, communities, cooperatives, and emerging farmers through accredited learning, workplace support, and development projects.',
                    ],
                    'timeline' => [
                        ['label' => 'Vision',     'title' => 'Where we aim to be',  'desc' => 'To be a leading force in transforming agricultural communities through innovative training, sustainable practices, and empowerment.'],
                        ['label' => 'Mission',    'title' => 'How we work',          'desc' => 'To provide accredited training and development programmes that enhance skills, promote sustainable farming, and empower communities across South Africa.'],
                        ['label' => 'Leadership', 'title' => 'Who leads JNV',        'desc' => 'Led by Vhutshilo Madzunye and Ignatia Khumalo, with strong experience in agricultural management, training, assessment, moderation, and compliance.'],
                    ],
                    'stats' => [
                        ['value' => '2013',     'label' => 'Year established',     'valueClass' => 'text-harvest'],
                        ['value' => '19+',      'label' => 'Years of leadership',  'valueClass' => 'text-green'],
                        ['value' => 'AgriSETA', 'label' => 'Accredited provider',  'valueClass' => 'text-white text-2xl md:text-3xl'],
                        ['value' => 'QCTO',     'label' => 'Accredited provider',  'valueClass' => 'text-white text-2xl md:text-3xl'],
                    ],
                ],
            ],

            // ── Training ──────────────────────────────────────────────────
            'training' => [
                'eyebrow'        => 'Training and Development',
                'title'          => 'Programmes built for workplace relevance.',
                'body'           => 'We deliver accredited and non-accredited interventions aligned to industry needs, with a strong focus on practical learning, workplace exposure, and measurable outcomes.',
                'image'          => '/images/training.jpg',
                'seoTitle'       => 'Training and Development — JNV',
                'seoDescription' => 'Accredited training programmes in agriculture, plant production, poultry, and workplace skills. Learnerships, skills programmes, and assessment support.',
                'content' => [
                    'programmeAreas' => [
                        [
                            'title'    => 'Agriculture and Farming',
                            'iconPath' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                            'items'    => ['Plant Production', 'Poultry Production', 'Poultry Processing', 'Farming qualifications and skills programmes'],
                        ],
                        [
                            'title'    => 'Business and Workplace Skills',
                            'iconPath' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                            'items'    => ['Agricultural Sales and Services', 'Workplace compliance training', 'Short skills programmes', 'Occupational and practical learning support'],
                        ],
                    ],
                    'deliveryTypes' => [
                        ['title' => 'Learnerships',        'desc' => 'Structured programmes linked to workplace application and formal outcomes.'],
                        ['title' => 'Skills Programmes',   'desc' => 'Targeted interventions for projects, employers, and community development.'],
                        ['title' => 'Short Courses',       'desc' => 'Focused training for operational, compliance, and supervisory needs.'],
                        ['title' => 'Assessment Support',  'desc' => 'Facilitation, workplace observation, evidence support, and moderation.'],
                    ],
                ],
            ],

            // ── Compliance ────────────────────────────────────────────────
            'compliance' => [
                'eyebrow'        => 'SDF and Compliance',
                'title'          => 'Support that keeps you aligned and funding ready.',
                'body'           => 'We help organisations manage core skills development obligations and training compliance requirements with practical, responsive support.',
                'image'          => '/images/compliance.jpg',
                'seoTitle'       => 'SDF and Compliance Support — JNV',
                'seoDescription' => 'Skills development facilitation, WSP/ATR submissions, levy claims, and compliance support for South African employers. JNV Training and Development.',
                'content' => [
                    'coreAreas' => [
                        ['label' => '01', 'title' => 'WSP and ATR',           'desc' => 'Planning, submissions, and updates aligned to your workplace needs and SETA requirements.'],
                        ['label' => '02', 'title' => 'Skills Audits',         'desc' => 'Evidence-based analysis to guide training priorities and reporting for your organisation.'],
                        ['label' => '03', 'title' => 'Grant and Levy Support','desc' => 'Practical assistance for funding-linked processes and mandatory/discretionary grant claims.'],
                    ],
                    'services' => [
                        [
                            'title' => 'Skills Development Facilitation',
                            'items' => ['WSP submissions', 'ATR submissions', 'Training planning', 'Workplace skills alignment'],
                        ],
                        [
                            'title' => 'Compliance and Quality Support',
                            'items' => ['Training records and evidence support', 'Audit preparation', 'Quality assurance guidance', 'Accreditation support'],
                        ],
                        [
                            'title' => 'Project Oversight',
                            'items' => ['Monitoring and evaluation', 'Reporting support', 'Learner tracking', 'Implementation guidance'],
                        ],
                    ],
                ],
            ],

            // ── Farm Development ──────────────────────────────────────────
            'farm-development' => [
                'eyebrow'        => 'Farm Development',
                'title'          => 'From concept to implementation.',
                'body'           => 'We support agricultural development projects that need more than classroom training. Our farm development service helps clients move from planning to working operations.',
                'image'          => '/images/farm-development.jpg',
                'seoTitle'       => 'Farm Development — JNV Training and Development',
                'seoDescription' => 'Agricultural farm development services from planning to implementation. Crop systems, packhouse support, cooperative development, and emerging farmer support.',
                'content' => [
                    'whatWeDo' => [
                        'Project planning and feasibility support',
                        'Site assessment and land use planning',
                        'Packhouse and post-harvest support',
                        'Crop and livestock systems',
                        'Training linked to production activities',
                        'Support for cooperatives, communities, and emerging farmers',
                    ],
                    'processSteps' => [
                        ['title' => 'Assess',     'desc' => 'Review project needs, location, targets, and operational requirements to establish a clear baseline.'],
                        ['title' => 'Design',     'desc' => 'Structure training, development outputs, and implementation pathways aligned to client objectives.'],
                        ['title' => 'Implement',  'desc' => 'Deliver training, on-site support, and practical systems that work in the field.'],
                        ['title' => 'Support',    'desc' => 'Provide ongoing monitoring, mentoring, and compliance guidance throughout the project lifecycle.'],
                    ],
                ],
            ],

            // ── Projects ─────────────────────────────────────────────────
            'projects' => [
                'eyebrow'        => 'Projects and Clients',
                'title'          => 'Trusted by public and private sector partners.',
                'body'           => 'JNV has delivered learnerships, skills programmes, and training projects for commercial clients, public sector programmes, and agricultural development initiatives across multiple provinces.',
                'image'          => '/images/projects.jpg',
                'seoTitle'       => 'Projects and Clients — JNV Training and Development',
                'seoDescription' => 'JNV has delivered learnerships, skills programmes, and training projects for AgriSETA, Milk SA, Rand Water, and other public and private sector clients.',
                'content' => [
                    'strengths' => [
                        ['title' => 'Learnership delivery',    'desc' => 'Structured programmes linked to workplace support and completion quality.'],
                        ['title' => 'Skills programme delivery','desc' => 'Flexible project-based support for employers and development partners.'],
                        ['title' => 'Compliance reporting',    'desc' => 'Evidence management, monitoring, and quality-focused oversight.'],
                    ],
                    'projectTypes' => [
                        [
                            'title'    => 'Learnership Delivery',
                            'desc'     => 'Delivery across agriculture, horticulture, plant production, and related programmes with employer and funder alignment.',
                            'iconPath' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                        ],
                        [
                            'title'    => 'Skills Programme Delivery',
                            'desc'     => 'Project-based training for municipalities, development agencies, and workplace clients with practical support.',
                            'iconPath' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                        ],
                        [
                            'title'    => 'Compliance and Reporting',
                            'desc'     => 'Monitoring, reporting, evidence management, and quality support for funded and employer-led projects.',
                            'iconPath' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                        ],
                    ],
                    'clients' => ['AgriSETA', 'Milk SA', 'Rand Water', 'Dept of Public Works', 'Hadeco', 'Tranlabins', 'Dicla', 'Reatisa Tsebo'],
                ],
            ],

            // ── Contact ───────────────────────────────────────────────────
            'contact' => [
                'seoTitle'       => 'Contact JNV Training and Development',
                'seoDescription' => 'Get in touch with JNV Training and Development. Training, compliance, and farm development enquiries welcome. Based in Ga-Rankuwa, Pretoria.',
                'content'        => (object) [],
            ],

            // ── Downloads ─────────────────────────────────────────────────
            'downloads' => [
                'seoTitle'       => 'Newsletters & Downloads — JNV Training and Development',
                'seoDescription' => 'Download JNV newsletters, training brochures, learnership information packs, and compliance documents.',
                'content'        => (object) [],
            ],

        ];
    }
}
