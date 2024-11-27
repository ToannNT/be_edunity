<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy danh sách user IDs
        $userIds = User::pluck('id')->toArray();
        $randomUserId = function() use ($userIds) {
            return $userIds[array_rand($userIds)];
        };

        // Mảng danh mục cha và con
        $categories = [
            [
                'name' => 'Graphic Design',
                'image' => 'https://maytinhhaiphong.com/wp-content/uploads/2022/10/Cong-viec-Designer.jpg',
                'icon' => 'faPaintBrush',
                'children' => [
                    [
                        'name' => 'Photoshop',
                        'image' => 'https://example.com/path/to/photoshop.jpg',
                        'icon' => 'faImage',
                        'keyword' => 'graphic, design, photoshop',
                        'description' => 'Khóa học Photoshop dành cho người mới bắt đầu.'
                    ],
                    [
                        'name' => 'Adobe Illustrator',
                        'image' => 'https://example.com/path/to/adobe-illustrator.jpg',
                        'icon' => 'faRectangleAd',
                        'keyword' => 'graphic, design, illustrator',
                        'description' => 'Khóa học Adobe Illustrator để tạo hình ảnh vector.'
                    ],
                    [
                        'name' => 'Canva',
                        'image' => 'https://example.com/path/to/canva.jpg',
                        'icon' => 'faC',
                        'keyword' => 'graphic, design, canva',
                        'description' => 'Khóa học Canva giúp bạn thiết kế đồ họa dễ dàng.'
                    ]
                ]
            ],
            [
                'name' => 'Web Development',
                'image' => 'CategoryImgWeb',
                'icon' => 'faLaptopCode',
                'children' => [
                    [
                        'name' => 'HTML & CSS',
                        'image' => 'https://example.com/path/to/html.jpg',
                        'icon' => 'faHtml5',
                        'keyword' => 'web, development, html, css',
                        'description' => 'Khóa học HTML & CSS từ cơ bản đến nâng cao.'
                    ],
                    [
                        'name' => 'JavaScript',
                        'image' => 'https://example.com/path/to/javascript.jpg',
                        'icon' => 'faJs',
                        'keyword' => 'web, development, javascript',
                        'description' => 'Khóa học JavaScript cho các nhà phát triển web.'
                    ]
                ]
            ],
            [
                'name' => 'Digital Marketing',
                'image' => 'https://getflycrm.com/wp-content/uploads/2015/12/digital-marketing.webp',
                'icon' => 'faBullhorn',
                'children' => [
                    [
                        'name' => 'SEO',
                        'image' => 'https://example.com/path/to/seo.jpg',
                        'icon' => 'faShopify',
                        'keyword' => 'marketing, seo',
                        'description' => 'Khóa học SEO cho doanh nghiệp nhỏ.'
                    ],
                    [
                        'name' => 'Social Media Marketing',
                        'image' => 'https://example.com/path/to/social-media.jpg',
                        'icon' => 'faPhotoFilm',
                        'keyword' => 'marketing, social media',
                        'description' => 'Khóa học tiếp thị trên mạng xã hội.'
                    ]
                ]
            ],
            [
                'name' => 'Data Science',
                'image' => 'https://www.cdmi.in/courses@2x/data-science.webp',
                'icon' => 'faDatabase',
                'children' => [
                    [
                        'name' => 'Python for Data Science',
                        'image' => 'https://example.com/path/to/python.jpg',
                        'icon' => 'faClipboardCheck',
                        'keyword' => 'data science, python',
                        'description' => 'Khóa học Python cho phân tích dữ liệu.'
                    ],
                    [
                        'name' => 'R Programming',
                        'image' => 'https://example.com/path/to/r.jpg',
                        'icon' => 'faClipboardCheck',
                        'keyword' => 'data science, r',
                        'description' => 'Khóa học R Programming cho phân tích dữ liệu.'
                    ]
                ]
            ],
            [
                'name' => 'Mobile Development',
                'image' => 'https://topdev.vn/blog/wp-content/uploads/2023/02/mobile-app-developer.png',
                'icon' => 'faMobileAlt',
                'children' => [
                    [
                        'name' => 'React Native',
                        'image' => 'https://example.com/path/to/react-native.jpg',
                        'icon' => 'faClipboardCheck',
                        'keyword' => 'mobile, react native',
                        'description' => 'Khóa học React Native để phát triển ứng dụng di động.'
                    ],
                    [
                        'name' => 'Flutter',
                        'image' => 'https://example.com/path/to/flutter.jpg',
                        'icon' => 'faClipboardCheck',
                        'keyword' => 'mobile, flutter',
                        'description' => 'Khóa học Flutter để phát triển ứng dụng di động đa nền tảng.'
                    ]
                ]
            ],
            [
                'name' => 'Advanced Graphic Design',
                'image' => 'https://sbitzone.com//assets/uploads//course-photo/course-photo-1587657593-sbiz.jpg',
                'icon' => 'faPaintBrush',
                'children' => [
                    [
                        'name' => 'Advanced Photoshop Techniques',
                        'image' => 'https://example.com/path/to/advanced-photoshop.jpg',
                        'icon' => 'faImage',
                        'keyword' => 'graphic, design, photoshop',
                        'description' => 'Khóa học nâng cao Photoshop với các kỹ thuật mới.'
                    ],
                    [
                        'name' => 'Illustrator Tips & Tricks',
                        'image' => 'https://example.com/path/to/illustrator-tips.jpg',
                        'icon' => 'faRectangleAd',
                        'keyword' => 'graphic, design, illustrator',
                        'description' => 'Khóa học mẹo và thủ thuật Illustrator.'
                    ]
                ]
            ],
            [
                'name' => 'Cyber Security',
                'image' => 'https://wiki.matbao.net/wp-content/uploads/2021/10/cyber-security-la-gi-02.jpg',
                'icon' => 'faBullhorn',
                'children' => [
                    [
                        'name' => 'Network Security Basics',
                        'image' => 'https://example.com/path/to/network-security.jpg',
                        'icon' => 'faClipboardCheck',
                        'keyword' => 'cyber security, network',
                        'description' => 'Khóa học cơ bản về bảo mật mạng.'
                    ],
                    [
                        'name' => 'Ethical Hacking',
                        'image' => 'https://example.com/path/to/ethical-hacking.jpg',
                        'icon' => 'faClipboardCheck',
                        'keyword' => 'cyber security, ethical hacking',
                        'description' => 'Khóa học hacking mũ trắng và bảo mật thông tin.'
                    ]
                ]
            ],
            [
                'name' => 'Photography',
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQBYXXb9i9n768sHkALYF8iQxH0xyUYMEJXfw&s',
                'icon' => 'faImage',
                'children' => [
                    [
                        'name' => 'Photography Basics',
                        'image' => 'https://example.com/path/to/basics.jpg',
                        'icon' => 'faCameraRetro',
                        'keyword' => 'photography, basics',
                        'description' => 'Khóa học về kỹ thuật chụp ảnh cơ bản.'
                    ],
                    [
                        'name' => 'Advanced Photography Techniques',
                        'image' => 'https://example.com/path/to/advanced-techniques.jpg',
                        'icon' => 'faCamera',
                        'keyword' => 'photography, advanced',
                        'description' => 'Khóa học kỹ thuật chụp ảnh nâng cao.'
                    ]
                ]
            ],
            [
                'name' => 'English Language Learning',
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRYeMnnkLWtI6pYS342oBrie7mN09auVclfHQ&s',
                'icon' => 'faClipboardCheck',
                'children' => [
                    [
                        'name' => 'English for Beginners',
                        'image' => 'https://example.com/path/to/beginner.jpg',
                        'icon' => 'faClipboardCheck',
                        'keyword' => 'language, english, beginners',
                        'description' => 'Khóa học tiếng Anh cho người mới bắt đầu.'
                    ],
                    [
                        'name' => 'Advanced English',
                        'image' => 'https://example.com/path/to/advanced.jpg',
                        'icon' => 'faClipboardCheck',
                        'keyword' => 'language, english, advanced',
                        'description' => 'Khóa học tiếng Anh nâng cao.'
                    ]
                ]
            ]
        ];
        

        // Insert danh mục cha trước và lưu lại id của chúng
        foreach ($categories as $category) {
            $parentId = DB::table('categories')->insertGetId([
                'name' => $category['name'],
                'image' => $category['image'],
                'icon' => $category['icon'],
                'keyword' => $category['keyword'] ?? null,
                'description' => $category['description'] ?? null,
                'status' => 'active',
                'parent_id' => null,
                'created_by' => $randomUserId(),
                'updated_by' => $randomUserId(),
                'deleted_by' => null,
            ]);

            // Insert danh mục con nếu có
            if (isset($category['children'])) {
                foreach ($category['children'] as $child) {
                    DB::table('categories')->insert([
                        'name' => $child['name'],
                        'image' => $child['image'],
                        'icon' => $child['icon'],
                        'keyword' => $child['keyword'] ?? null,
                        'description' => $child['description'] ?? null,
                        'status' => 'active',
                        'parent_id' => $parentId,
                        'created_by' => $randomUserId(),
                        'updated_by' => $randomUserId(),
                        'deleted_by' => null,
                    ]);
                }
            }
        }
    }
}
