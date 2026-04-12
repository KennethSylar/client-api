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

            'site_name'        => 'JNV Training and Development',
            'site_tagline'     => 'Building skills, developing farms, delivering impact.',
            'contact_email'    => 'hello@jnv.co.za',
            'contact_phone'    => '+27 (0) 21 000 0000',
            'contact_address'  => 'Cape Town, South Africa',

            'accreditations'   => json_encode([
                ['name' => 'SETA Accredited', 'logo' => ''],
                ['name' => 'QCTO Accredited', 'logo' => ''],
            ]),
        ];

        foreach ($settings as $key => $value) {
            $existing = $this->db->table('settings')->where('key', $key)->get()->getRowArray();
            if (!$existing) {
                $this->db->table('settings')->insert(['key' => $key, 'value' => $value]);
            }
        }

        echo "  Settings seeded.\n";
    }

    // ----------------------------------------------------------------
    // Pages — initial content for all 8 built-in pages
    // ----------------------------------------------------------------

    private function seedPages(): void
    {
        $pages = $this->builtinPages();

        foreach ($pages as $slug => $data) {
            $existing = $this->db->table('pages')->where('slug', $slug)->get()->getRowArray();
            if (!$existing) {
                $this->db->table('pages')->insert([
                    'slug'       => $slug,
                    'data'       => json_encode($data),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                echo "  Page '{$slug}' seeded.\n";
            } else {
                echo "  Page '{$slug}' already exists — skipped.\n";
            }
        }
    }

    private function builtinPages(): array
    {
        return [

            // ── Home ──────────────────────────────────────────────────────
            'home' => [
                'seoTitle'       => 'JNV Training and Development | Home',
                'seoDescription' => 'JNV Training and Development — empowering South African communities through skills training, SDF compliance and farm worker development.',
                'content' => [
                    'hero' => [
                        'tagline'   => 'South African training & development partner',
                        'heading'   => 'Building skills, developing farms, delivering impact.',
                        'body'      => 'JNV Training and Development empowers individuals, businesses and farming communities with accredited SETA skills programmes, SDF compliance support and targeted farm development initiatives.',
                        'ctaLabel'  => 'Start Your Project',
                        'imageUrl'  => '/images/hero.jpg',
                        'pills'     => ['SETA Accredited', 'QCTO Registered', 'Farm Development'],
                    ],
                    'mediaSection' => [
                        'eyebrow'    => 'What We Do',
                        'heading'    => 'Practical training that creates lasting change.',
                        'body'       => 'We design and deliver accredited training programmes tailored to the unique needs of South African businesses and farming operations.',
                        'imageUrl'   => '/images/about.jpg',
                        'mediaItems' => [
                            'Accredited skills programmes (SETA/QCTO)',
                            'Skills Development Facilitation (SDF)',
                            'Farm worker development & compliance',
                            'Workplace coaching and facilitation',
                        ],
                    ],
                    'aboutStrip' => [
                        'eyebrow'   => 'About JNV',
                        'heading'   => 'Two decades of skills development experience.',
                        'body'      => 'JNV Training and Development was founded on a simple belief: that access to quality training transforms lives, businesses and entire communities.',
                        'body2'     => 'From the Winelands to the Northern Cape, we have partnered with farmers, cooperatives and corporate clients to build meaningful, measurable skills.',
                        'linkLabel' => 'Learn about our story',
                    ],
                    'services' => [
                        'eyebrow' => 'Core Services',
                        'heading' => 'Everything you need for skills compliance and development.',
                        'body'    => 'Our three service pillars cover the full spectrum of workplace training and development.',
                        'items'   => [
                            [
                                'num'   => '01',
                                'title' => 'Training & Development',
                                'items' => [
                                    'Accredited SETA/QCTO learnerships',
                                    'Short skills programmes',
                                    'Workplace facilitation',
                                    'RPL assessments',
                                ],
                            ],
                            [
                                'num'   => '02',
                                'title' => 'SDF & Compliance',
                                'items' => [
                                    'Skills Development Facilitation',
                                    'WSP & ATR submissions',
                                    'BBBEE skills compliance',
                                    'Levy grant optimisation',
                                ],
                            ],
                            [
                                'num'   => '03',
                                'title' => 'Farm Development',
                                'items' => [
                                    'Farm worker skills programmes',
                                    'Agricultural compliance',
                                    'Literacy & numeracy',
                                    'Supervisory development',
                                ],
                            ],
                        ],
                    ],
                    'whyJnv' => [
                        'eyebrow' => 'Why JNV',
                        'heading' => 'Results-driven, relationship-focused.',
                        'items'   => [
                            ['title' => 'Accredited', 'desc' => 'All programmes are SETA and QCTO accredited, ensuring recognised qualifications.'],
                            ['title' => 'Experienced', 'desc' => 'Over 20 years delivering training across agriculture, retail and corporate sectors.'],
                            ['title' => 'Trusted', 'desc' => 'Long-term partnerships with clients across the Western and Northern Cape.'],
                            ['title' => 'Practical', 'desc' => 'Workplace-based learning that produces real, measurable outcomes.'],
                        ],
                    ],
                    'stats' => [
                        'eyebrow' => 'By the numbers',
                        'items'   => [
                            ['value' => '20+',   'label' => 'Years experience'],
                            ['value' => '5 000+','label' => 'Learners trained'],
                            ['value' => '50+',   'label' => 'Employer partners'],
                            ['value' => '15+',   'label' => 'Accredited programmes'],
                        ],
                    ],
                    'cta' => [
                        'eyebrow'  => 'Get started',
                        'heading'  => 'Ready to invest in your team?',
                        'body'     => 'Contact us today for a free consultation on your skills development needs.',
                        'ctaLabel' => 'Contact JNV',
                        'ctaHref'  => '/contact',
                    ],
                ],
            ],

            // ── About ─────────────────────────────────────────────────────
            'about' => [
                'eyebrow'        => 'About Us',
                'title'          => 'Our Story',
                'body'           => 'JNV Training and Development was founded on a belief that skills development can transform lives, businesses and communities.',
                'image'          => '/images/about.jpg',
                'seoTitle'       => 'About JNV | Training and Development',
                'seoDescription' => 'Learn about JNV Training and Development — our history, mission, values and the team behind South Africa\'s trusted skills development partner.',
                'content' => [
                    'blocks' => [],
                ],
            ],

            // ── Training ──────────────────────────────────────────────────
            'training' => [
                'eyebrow'        => 'Training & Development',
                'title'          => 'Accredited Skills Programmes',
                'body'           => 'We offer a comprehensive range of SETA and QCTO accredited skills programmes designed for the modern South African workplace.',
                'image'          => '/images/training.jpg',
                'seoTitle'       => 'Training and Development | JNV',
                'seoDescription' => 'SETA and QCTO accredited learnerships, short skills programmes and workplace facilitation from JNV Training and Development.',
                'content' => [
                    'blocks' => [],
                ],
            ],

            // ── Compliance (SDF) ──────────────────────────────────────────
            'compliance' => [
                'eyebrow'        => 'SDF & Compliance',
                'title'          => 'Skills Development Facilitation',
                'body'           => 'Expert SDF services to manage your workplace skills plan, annual training report and BBBEE compliance requirements.',
                'image'          => '/images/compliance.jpg',
                'seoTitle'       => 'SDF and Compliance | JNV',
                'seoDescription' => 'JNV provides expert Skills Development Facilitation (SDF) services including WSP/ATR submissions, levy grant optimisation and BBBEE compliance.',
                'content' => [
                    'blocks' => [],
                ],
            ],

            // ── Farm Development ──────────────────────────────────────────
            'farm-development' => [
                'eyebrow'        => 'Farm Development',
                'title'          => 'Developing Farm Communities',
                'body'           => 'Targeted training and development programmes that equip farm workers with the skills and knowledge to grow in their roles and careers.',
                'image'          => '/images/farm.jpg',
                'seoTitle'       => 'Farm Development | JNV',
                'seoDescription' => 'JNV delivers farm worker development programmes including accredited skills training, literacy and numeracy, and supervisory development.',
                'content' => [
                    'blocks' => [],
                ],
            ],

            // ── Projects ─────────────────────────────────────────────────
            'projects' => [
                'eyebrow'        => 'Projects & Clients',
                'title'          => 'Our Track Record',
                'body'           => 'Over two decades of delivering measurable impact for clients across the Western Cape, Northern Cape and beyond.',
                'image'          => '/images/projects.jpg',
                'seoTitle'       => 'Projects and Clients | JNV',
                'seoDescription' => 'Explore JNV\'s portfolio of training and development projects across agriculture, retail and corporate sectors.',
                'content' => [
                    'blocks' => [],
                ],
            ],

            // ── Contact ───────────────────────────────────────────────────
            'contact' => [
                'eyebrow'        => 'Get In Touch',
                'title'          => 'Contact JNV',
                'body'           => 'Have a project in mind? Get in touch — we\'ll respond within one business day.',
                'image'          => '/images/contact.jpg',
                'seoTitle'       => 'Contact | JNV Training and Development',
                'seoDescription' => 'Contact JNV Training and Development. We\'re here to help with skills training, SDF compliance and farm development enquiries.',
                'content'        => [],
            ],

            // ── Downloads ─────────────────────────────────────────────────
            'downloads' => [
                'eyebrow'        => 'Resources',
                'title'          => 'Downloads',
                'body'           => 'Access our newsletters, programme brochures and compliance documents.',
                'image'          => '/images/downloads.jpg',
                'seoTitle'       => 'Downloads | JNV Training and Development',
                'seoDescription' => 'Download JNV newsletters, programme brochures and skills development resources.',
                'content'        => [],
            ],

        ];
    }
}
