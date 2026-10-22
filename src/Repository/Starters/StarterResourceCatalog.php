<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Starters;

use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * The FREE starter Design Resource catalog (Phase 26.46).
 *
 * Real, renderable resources — curated pages and sections composed ENTIRELY from
 * registered built-in section/block types (see {@see \Plugins\PageBuilder\Support\DefaultLibrary}).
 * Nothing here forks the model: every entry is a plain {@see DesignResource} with a
 * renderable, id-free LayoutPayload, so the Runtime Preview Engine and Full Page
 * Import consume them directly.
 *
 * Content is deterministic, generic placeholder copy — no business identity, no
 * hardcoded colors (visual variety comes from section `style` presets), and every
 * asset is a safe root-relative URL. Users edit everything after import.
 */
final class StarterResourceCatalog
{
    public const SOURCE = 'builtin';

    private const VERSION = '1.0.0';

    /**
     * Vietnamese catalog metadata keyed by resourceId (PB-FREE-LIBRARY-DESIGN-1-F).
     *
     * The invariant English name/description stay inline at each resource (single
     * source); this map is the VI-only companion. The two are merged into the
     * resource's `localized` maps ({@see localizedMetadata()}) and resolved by Admin
     * UI locale at the catalog presentation boundary ({@see \Plugins\PageBuilder\Support\ResourceMetadataLocalizer}).
     * Machine fields (rid/kind/category/tags/version/payload) stay locale-invariant.
     *
     * @var array<string, array{name: string, description: string}>
     */
    private const VI_METADATA = [
        // Pages
        'company-home' => [
            'name' => 'Trang chủ doanh nghiệp',
            'description' => 'Trang chủ doanh nghiệp đáng tin cậy: hero giá trị, dải thương hiệu tin dùng đặt sớm, dịch vụ, cách vận hành, minh chứng khách hàng và CTA liên hệ.',
        ],
        'agency-home' => [
            'name' => 'Trang chủ agency',
            'description' => 'Trang studio sáng tạo cao cấp: hero chia đôi gắn hình ảnh, dải minh chứng, tác phẩm chọn lọc dẫn dắt bằng hình ảnh, năng lực rõ ràng, quy trình đánh số, dấu ấn đối tác chỉn chu, một lời chứng thực đáng tin và lời mời hợp tác dứt khoát.',
        ],
        'saas-landing' => [
            'name' => 'Trang đích SaaS',
            'description' => 'Trang SaaS hướng sản phẩm: hero theo kết quả, ảnh sản phẩm, lợi ích, quy trình ba bước, minh chứng, bảng giá có gói khuyến nghị, câu hỏi thường gặp và CTA đăng ký.',
        ],
        'portfolio' => [
            'name' => 'Portfolio cá nhân',
            'description' => 'Portfolio cá nhân lấy tác phẩm làm trọng tâm: hero định vị điềm tĩnh, thư viện dự án có chú thích dẫn dắt, bộ kỹ năng gọn, phần giới thiệu ngắn và CTA liên hệ.',
        ],
        'blog-home' => [
            'name' => 'Trang chủ blog',
            'description' => 'Trang blog kiểu biên tập: bài viết dẫn, bài nổi bật, khám phá chủ đề, bài viết gần đây, ghi chú giới thiệu và CTA đăng ký.',
        ],
        // Sections
        'hero-modern' => [
            'name' => 'Hero hiện đại',
            'description' => 'Khu hero nền tối, nổi bật với tiêu đề, dòng phụ và một hành động chính.',
        ],
        'hero-company' => [
            'name' => 'Hero doanh nghiệp',
            'description' => 'Khu hero doanh nghiệp điềm đạm trên nền bề mặt nhẹ.',
        ],
        'hero-saas' => [
            'name' => 'Hero SaaS',
            'description' => 'Khu hero sản phẩm kết hợp tiêu đề với ảnh chụp giao diện.',
        ],
        'feature-grid' => [
            'name' => 'Lưới tính năng',
            'description' => 'Lưới ba cột gồm các điểm nổi bật tính năng ngắn gọn.',
        ],
        'feature-cards' => [
            'name' => 'Thẻ tính năng',
            'description' => 'Một hàng thẻ tính năng, mỗi thẻ có tiêu đề, mô tả và hành động.',
        ],
        'testimonials' => [
            'name' => 'Nhận xét khách hàng',
            'description' => 'Ba nhận xét của khách hàng trình bày thành các thẻ đều nhau, gọn gàng.',
        ],
        'logo-strip' => [
            'name' => 'Dải logo',
            'description' => 'Một hàng logo khách hàng hoặc đối tác dưới một tiêu đề ngắn, tạo sự tin cậy.',
        ],
        'cta-banner' => [
            'name' => 'Banner kêu gọi hành động',
            'description' => 'Dải kêu gọi hành động tập trung với tiêu đề và nút bấm.',
        ],
        'pricing' => [
            'name' => 'Bảng giá',
            'description' => 'Một hàng bảng giá ba bậc với gói khuyến nghị được làm nổi bật.',
        ],
    ];

    /** @var array<string, DesignResource>|null */
    private ?array $byRid = null;

    /**
     * @return array<int, DesignResource>
     */
    public function all(): array
    {
        return [...$this->pages(), ...$this->sections()];
    }

    public function find(string $rid): ?DesignResource
    {
        if ($this->byRid === null) {
            $this->byRid = [];
            foreach ($this->all() as $resource) {
                $this->byRid[$resource->rid] = $resource;
            }
        }

        return $this->byRid[$rid] ?? null;
    }

    // ---- Pages ---------------------------------------------------------------

    /**
     * @return array<int, DesignResource>
     */
    public function pages(): array
    {
        return [
            // 1) COMPANY — calm enterprise. Signature: trust strip above the fold,
            // even measured rhythm, surface hero. hero,logo-strip,section,columns,section,columns,testimonial,cta
            $this->page('company-home', 'Company Homepage', 'business', ['business', 'corporate', 'homepage'],
                'A credible corporate homepage: value hero, early trust strip, services, operating model, customer proof and a contact CTA.', [
                    $this->pageHeroNode(
                        $this->t('Software that keeps your business moving', 'Phần mềm giữ cho doanh nghiệp của bạn luôn vận hành'),
                        $this->t('We help established teams modernise their web platforms with reliable, maintainable software — delivered on a schedule you can plan around.', 'Chúng tôi giúp các đội ngũ giàu kinh nghiệm hiện đại hoá nền tảng web bằng phần mềm đáng tin cậy, dễ bảo trì — theo một lộ trình bạn có thể chủ động lên kế hoạch.'),
                        $this->t('Get started', 'Bắt đầu'), $this->t('Talk to sales', 'Trao đổi với đội ngũ kinh doanh'), 'surface'),
                    $this->pageLogoStripNode($this->t('Trusted by operations teams at growing companies', 'Được các đội vận hành tại những công ty đang tăng trưởng tin dùng')),
                    $this->introBand(
                        $this->t('What we do', 'Chúng tôi làm gì'),
                        $this->t('Three focused services that cover the whole lifecycle of a modern web platform.', 'Ba dịch vụ trọng tâm bao trùm toàn bộ vòng đời của một nền tảng web hiện đại.')),
                    $this->cardsNode([
                        [$this->t('Consulting', 'Tư vấn'), $this->t('We turn your goals into a pragmatic technical plan your team can act on with confidence.', 'Chúng tôi biến mục tiêu của bạn thành một kế hoạch kỹ thuật thực tế để đội ngũ triển khai một cách tự tin.')],
                        [$this->t('Implementation', 'Triển khai'), $this->t('We build accessible, server-rendered platforms that stay easy to maintain as you grow.', 'Chúng tôi xây dựng nền tảng dễ tiếp cận, kết xuất phía máy chủ và luôn dễ bảo trì khi bạn mở rộng.')],
                        [$this->t('Ongoing support', 'Hỗ trợ lâu dài'), $this->t('We stay on to keep everything fast, secure and up to date — no surprises.', 'Chúng tôi đồng hành để mọi thứ luôn nhanh, an toàn và cập nhật — không có bất ngờ khó chịu.')],
                    ], 'surface', 'lg', '33'),
                    $this->introBand(
                        $this->t('How we work with you', 'Cách chúng tôi làm việc cùng bạn'),
                        $this->t('A calm, transparent way of working built around your people.', 'Một cách làm việc điềm tĩnh, minh bạch và lấy con người của bạn làm trung tâm.')),
                    $this->valueStripNode([
                        [$this->t('Clear scope', 'Phạm vi rõ ràng'), $this->t('Fixed, well-defined phases.', 'Các giai đoạn cố định, được xác định rõ.')],
                        [$this->t('Honest pricing', 'Chi phí minh bạch'), $this->t('No hidden line items.', 'Không có khoản phí ẩn.')],
                        [$this->t('Accessible by default', 'Dễ tiếp cận mặc định'), $this->t('WCAG-minded from day one.', 'Tuân thủ WCAG ngay từ đầu.')],
                        [$this->t('Yours to own', 'Thuộc quyền của bạn'), $this->t('Documented, handover-ready.', 'Tài liệu đầy đủ, sẵn sàng bàn giao.')],
                    ]),
                    $this->pageTestimonialsNode([
                        [$this->t('They understood our constraints and delivered exactly what we needed — on time.', 'Họ hiểu rõ các ràng buộc của chúng tôi và bàn giao đúng những gì cần thiết — đúng hạn.'), 'Priya Anand', $this->t('Operations Director', 'Giám đốc Vận hành')],
                        [$this->t('A rare partner that leaves you with something maintainable, not a black box.', 'Một đối tác hiếm có: để lại cho bạn thứ dễ bảo trì, chứ không phải một hộp đen.'), 'Marcus Bell', $this->t('CTO', 'Giám đốc Công nghệ')],
                    ]),
                    $this->pageCtaNode(
                        $this->t('Ready to modernise your platform?', 'Sẵn sàng hiện đại hoá nền tảng của bạn?'),
                        $this->t('Book a short, no-pressure call and we will show you what is possible.', 'Hãy đặt một cuộc trao đổi ngắn, không áp lực và chúng tôi sẽ cho bạn thấy những gì có thể làm được.'),
                        $this->t('Get started', 'Bắt đầu')),
                ]),
            // 2) AGENCY — premium editorial creative studio (PB-DEMO-DESIGN-R1-A).
            // Signature: a media-anchored SPLIT hero and a cobalt accent carried through
            // the whole subtree; a proof strip, image-led selected work, differentiated
            // capabilities, a four-step numbered process, intentional partner wordmarks,
            // one credible testimonial and a decisive enquiry.
            // hero(split),columns(metrics),section,columns(cases),section,columns(caps),
            // section,columns(process),testimonial,logo-strip,cta.
            $this->page('agency-home', 'Agency Homepage', 'agency', ['agency', 'studio', 'creative'],
                'A premium creative-studio homepage: a media-anchored split hero, a proof strip, image-led selected work, clear capabilities, a real numbered process, intentional partner marks, a credible testimonial and a decisive project enquiry.', [
                    $this->agencyHeroNode(
                        $this->t('Independent creative studio', 'Studio sáng tạo độc lập'),
                        $this->t('We turn ambitious ideas into work that performs', 'Chúng tôi biến ý tưởng tham vọng thành tác phẩm tạo ra kết quả'),
                        $this->t('A brand, product and web studio for teams who want more than a template — crafted, measured, and shipped with care.', 'Một studio thương hiệu, sản phẩm và web cho những đội ngũ muốn nhiều hơn một mẫu có sẵn — chỉn chu, đo lường được và hoàn thiện bằng sự tỉ mỉ.'),
                        $this->t('See our work', 'Xem tác phẩm'),
                        $this->t('Start a project', 'Bắt đầu một dự án')),
                    $this->agencyMetricsNode([
                        [$this->t('12 yrs', '12 năm'), $this->t('Independent practice', 'Hành nghề độc lập')],
                        [$this->t('40+', '40+'), $this->t('Brands & products shipped', 'Thương hiệu & sản phẩm đã ra mắt')],
                        [$this->t('3', '3'), $this->t('Disciplines under one roof', 'Lĩnh vực dưới một mái nhà')],
                    ]),
                    $this->agencyIntroNode(
                        $this->t('Selected work', 'Tác phẩm chọn lọc'),
                        $this->t('Projects we are proud to put our name on', 'Những dự án chúng tôi tự hào ghi tên mình'),
                        $this->t('A sample of recent brand, product and web projects — each one fully editable after you import this page.', 'Một vài dự án thương hiệu, sản phẩm và web gần đây — tất cả đều có thể chỉnh sửa trọn vẹn sau khi bạn nhập trang này.')),
                    $this->agencyCaseCardsNode([
                        [StarterAssets::MEDIA_CASE_01, $this->t('Brand system', 'Hệ thống thương hiệu'), $this->t('Northwind', 'Northwind'), $this->t('A complete identity and marketing site for a logistics platform — clearer positioning and measurably faster launches.', 'Bộ nhận diện hoàn chỉnh và trang tiếp thị cho một nền tảng logistics — định vị rõ ràng hơn và ra mắt nhanh hơn thấy rõ.'), $this->t('View case', 'Xem chi tiết')],
                        [StarterAssets::MEDIA_CASE_02, $this->t('Campaign', 'Chiến dịch'), $this->t('Brightpath', 'Brightpath'), $this->t('A launch site and motion system for a seasonal product campaign.', 'Trang ra mắt và hệ thống đồ hoạ chuyển động cho một chiến dịch sản phẩm theo mùa.'), $this->t('View case', 'Xem chi tiết')],
                        [StarterAssets::MEDIA_CASE_03, $this->t('Product UI', 'Giao diện sản phẩm'), $this->t('Lumen', 'Lumen'), $this->t('A design system and app interface for a fast-growing analytics tool.', 'Hệ thống thiết kế và giao diện ứng dụng cho một công cụ phân tích tăng trưởng nhanh.'), $this->t('View case', 'Xem chi tiết')],
                    ]),
                    $this->agencyIntroNode(
                        $this->t('What we do', 'Chúng tôi làm gì'),
                        $this->t('Three disciplines, one accountable team', 'Ba lĩnh vực, một đội ngũ chịu trách nhiệm'),
                        $this->t('We cover the whole arc — from the first idea to the thing people actually use.', 'Chúng tôi bao trùm toàn bộ hành trình — từ ý tưởng đầu tiên đến thứ mà người dùng thực sự sử dụng.'), 'neutral'),
                    $this->agencyCapabilitiesNode([
                        ['brand', $this->t('Brand systems', 'Hệ thống thương hiệu'), $this->t('Identity, art direction and the guidelines that keep a brand coherent as it grows.', 'Nhận diện, định hướng mỹ thuật và bộ nguyên tắc giữ cho thương hiệu nhất quán khi lớn lên.')],
                        ['product', $this->t('Digital products', 'Sản phẩm số'), $this->t('Product design and design systems — accessible interfaces engineered to ship.', 'Thiết kế sản phẩm và hệ thống thiết kế — giao diện dễ tiếp cận, được xây dựng để đưa vào vận hành.')],
                        ['web', $this->t('Web & campaigns', 'Web & chiến dịch'), $this->t('Fast, server-rendered marketing sites and launch campaigns that convert.', 'Những trang tiếp thị kết xuất phía máy chủ nhanh nhạy và chiến dịch ra mắt tạo chuyển đổi.')],
                    ]),
                    $this->agencyIntroNode(
                        $this->t('How we work', 'Cách chúng tôi làm việc'),
                        $this->t('A focused process that keeps ideas sharp', 'Một quy trình tập trung giữ cho ý tưởng sắc bén'),
                        $this->t('Four clear stages, so you always know exactly where a project stands.', 'Bốn giai đoạn rõ ràng, để bạn luôn biết chính xác dự án đang ở đâu.'), 'ink'),
                    $this->agencyProcessNode([
                        ['strategy', $this->t('Discover', 'Khám phá'), $this->t('Research, positioning and the problem worth solving.', 'Nghiên cứu, định vị và vấn đề đáng để giải quyết.')],
                        ['brand', $this->t('Design', 'Thiết kế'), $this->t('Identity and interface, explored and pressure-tested.', 'Nhận diện và giao diện, khám phá và kiểm chứng kỹ lưỡng.')],
                        ['build', $this->t('Build', 'Phát triển'), $this->t('Accessible, production-ready front-end you can own.', 'Front-end dễ tiếp cận, sẵn sàng vận hành và thuộc về bạn.')],
                        ['launch', $this->t('Launch', 'Ra mắt'), $this->t('Ship, measure and refine against real results.', 'Ra mắt, đo lường và tinh chỉnh theo kết quả thực tế.')],
                    ]),
                    $this->agencyTestimonialNode(
                        $this->t('They gave our brand a genuine point of view — and sales noticed within the first month.', 'Họ mang đến cho thương hiệu của chúng tôi một quan điểm thực sự — và doanh số nhận ra ngay trong tháng đầu.'),
                        'Sofia Marin',
                        $this->t('Founder, Northwind', 'Nhà sáng lập, Northwind')),
                    $this->agencyMarksNode($this->t('A few of the teams we have partnered with', 'Một vài đội ngũ chúng tôi đã đồng hành')),
                    $this->agencyCtaNode(
                        $this->t('Have a project in mind?', 'Bạn đang ấp ủ một dự án?'),
                        $this->t('Tell us what you are building and we will help you ship it beautifully. No forms, no pressure — just a conversation.', 'Hãy cho chúng tôi biết bạn đang xây dựng điều gì, chúng tôi sẽ giúp bạn hoàn thiện nó thật đẹp. Không biểu mẫu, không áp lực — chỉ là một cuộc trò chuyện.'),
                        $this->t('Start a project', 'Bắt đầu một dự án')),
                ]),
            // 3) SAAS — product-led. Signature: product media band, recommended-tier
            // card, FAQ. hero,image-section,section,columns,section,columns,testimonial,section,columns,faq,cta
            $this->page('saas-landing', 'SaaS Landing', 'saas', ['saas', 'product', 'landing'],
                'A product-led SaaS page: an outcome hero, product media, benefits, a three-step workflow, proof, pricing with a recommended tier, FAQ and a signup CTA.', [
                    $this->pageHeroNode(
                        $this->t('Everything your team needs to plan, build and ship', 'Mọi thứ đội ngũ của bạn cần để lập kế hoạch, xây dựng và ra mắt'),
                        $this->t('One shared workspace that replaces the scattered tools slowing your team down — so work moves in one place.', 'Một không gian làm việc chung thay thế cho mớ công cụ rời rạc đang làm chậm đội ngũ của bạn — để công việc diễn ra ở một nơi duy nhất.'),
                        $this->t('Start free', 'Dùng thử miễn phí'), $this->t('Book a demo', 'Đặt lịch demo'), 'plain'),
                    $this->mediaBandNode($this->t('Product interface preview', 'Xem trước giao diện sản phẩm'), $this->t('A quick look at the workspace', 'Một thoáng nhìn về không gian làm việc')),
                    $this->introBand(
                        $this->t('Built for the way teams actually work', 'Được thiết kế đúng theo cách các đội ngũ thực sự làm việc'),
                        $this->t('Fewer tabs, less busywork, more shipped.', 'Ít tab hơn, ít việc vụn vặt hơn, hoàn thành nhiều hơn.')),
                    $this->cardsNode([
                        [$this->t('Instant loads', 'Tải tức thì'), $this->t('Server-rendered pages mean no spinner and no waiting around.', 'Các trang kết xuất phía máy chủ nghĩa là không còn vòng xoay chờ đợi.')],
                        [$this->t('One shared space', 'Một không gian chung'), $this->t('Plans, docs and updates live together, visible to everyone.', 'Kế hoạch, tài liệu và cập nhật nằm cùng một chỗ, ai cũng thấy được.')],
                        [$this->t('Adapts to you', 'Thích ứng với bạn'), $this->t('Compose your own views from reusable, editable blocks.', 'Tự tạo các khung hiển thị của riêng bạn từ những khối có thể tái dùng và chỉnh sửa.')],
                    ], 'surface', 'md', '33'),
                    $this->introBand(
                        $this->t('From idea to shipped in three steps', 'Từ ý tưởng đến ra mắt chỉ trong ba bước'),
                        $this->t('A simple flow your whole team can follow.', 'Một quy trình đơn giản để cả đội ngũ cùng theo.')),
                    $this->valueStripNode([
                        [$this->t('1 · Plan', '1 · Lập kế hoạch'), $this->t('Capture the work in one place.', 'Ghi lại công việc ở một nơi.')],
                        [$this->t('2 · Build', '2 · Xây dựng'), $this->t('Collaborate without the handoffs.', 'Cộng tác mà không cần bàn giao qua lại.')],
                        [$this->t('3 · Ship', '3 · Ra mắt'), $this->t('Publish and share in a click.', 'Xuất bản và chia sẻ chỉ bằng một cú nhấp.')],
                    ]),
                    $this->pageTestimonialsNode([
                        [$this->t('We consolidated three subscriptions into one and the team actually prefers it.', 'Chúng tôi gộp ba gói đăng ký thành một và cả đội còn thích hơn trước.'), 'Alex Rivera', $this->t('Product Lead', 'Trưởng nhóm Sản phẩm')],
                        [$this->t('Onboarding took an afternoon. That never happens.', 'Việc làm quen chỉ mất một buổi chiều. Điều đó chưa từng xảy ra.'), 'Jordan Lee', $this->t('Founder', 'Nhà sáng lập')],
                    ]),
                    $this->introBand(
                        $this->t('Simple, predictable pricing', 'Bảng giá đơn giản, dễ dự đoán'),
                        $this->t('Start free. Upgrade only when it pays for itself.', 'Bắt đầu miễn phí. Chỉ nâng cấp khi nó thực sự xứng đáng.')),
                    $this->pagePricingNode([
                        [$this->t('Starter', 'Khởi đầu'), $this->t('$0 / forever', '0đ / mãi mãi'), [$this->t('1 project', '1 dự án'), $this->t('Community support', 'Hỗ trợ cộng đồng'), $this->t('Starter blocks', 'Bộ khối cơ bản')], $this->t('Get started', 'Bắt đầu'), 'outline', 'surface'],
                        [$this->t('Pro', 'Chuyên nghiệp'), $this->t('$29 / month', '29$ / tháng'), [$this->t('Unlimited projects', 'Dự án không giới hạn'), $this->t('Priority support', 'Hỗ trợ ưu tiên'), $this->t('All blocks & patterns', 'Toàn bộ khối & mẫu')], $this->t('Start free trial', 'Dùng thử miễn phí'), 'primary', 'card'],
                        [$this->t('Scale', 'Mở rộng'), $this->t('Custom', 'Theo yêu cầu'), [$this->t('SSO & roles', 'SSO & phân quyền'), $this->t('Dedicated support', 'Hỗ trợ chuyên trách'), $this->t('SLA & onboarding', 'SLA & triển khai')], $this->t('Contact sales', 'Liên hệ kinh doanh'), 'outline', 'surface'],
                    ]),
                    $this->pageFaqNode($this->t('Frequently asked questions', 'Câu hỏi thường gặp'), [
                        [$this->t('Is there a free plan?', 'Có gói miễn phí không?'), $this->t('Yes — you can start for free and upgrade later.', 'Có — bạn có thể bắt đầu miễn phí và nâng cấp sau.')],
                        [$this->t('Can I edit everything after import?', 'Tôi có thể chỉnh sửa mọi thứ sau khi nhập không?'), $this->t('Absolutely. Every block and section is fully editable.', 'Hoàn toàn được. Mọi khối và mục đều có thể chỉnh sửa trọn vẹn.')],
                        [$this->t('Do I need to write code?', 'Tôi có cần viết mã không?'), $this->t('No. Pages are composed from reusable blocks.', 'Không. Trang được tạo nên từ các khối có thể tái dùng.')],
                    ]),
                    $this->pageCtaNode(
                        $this->t('Start building today', 'Bắt đầu xây dựng ngay hôm nay'),
                        $this->t('Free to try, no card required. Upgrade whenever you are ready.', 'Dùng thử miễn phí, không cần thẻ. Nâng cấp bất cứ khi nào bạn sẵn sàng.'),
                        $this->t('Start free', 'Dùng thử miễn phí')),
                ]),
            // 4) PORTFOLIO — minimal, work-first. Signature: the work gallery dominates.
            // hero,section,columns(gallery),section,columns,testimonial,section(about),cta
            $this->page('portfolio', 'Portfolio', 'portfolio', ['portfolio', 'personal', 'showcase'],
                'A work-forward personal portfolio: a quiet positioning hero, a captioned project gallery that leads, a compact skill set, a short about and a contact CTA.', [
                    $this->pageHeroNode(
                        $this->t('Designer and developer crafting calm digital products', 'Nhà thiết kế kiêm lập trình viên tạo nên những sản phẩm số điềm tĩnh'),
                        $this->t('I help founders and small teams turn rough ideas into polished, accessible products — end to end.', 'Tôi giúp các nhà sáng lập và đội ngũ nhỏ biến ý tưởng sơ khởi thành sản phẩm chỉn chu, dễ tiếp cận — từ đầu đến cuối.'),
                        $this->t('View work', 'Xem tác phẩm'), $this->t('Get in touch', 'Liên hệ'), 'surface'),
                    $this->introBand(
                        $this->t('Selected work', 'Tác phẩm chọn lọc'),
                        $this->t('A few recent projects. Each links to a fuller case study you can edit.', 'Một vài dự án gần đây. Mỗi mục dẫn tới một bản nghiên cứu đầy đủ mà bạn có thể chỉnh sửa.')),
                    $this->galleryNode([
                        [$this->t('Fieldnote', 'Fieldnote'), $this->t('Branding and marketing site for a research tool.', 'Thương hiệu và trang tiếp thị cho một công cụ nghiên cứu.')],
                        [$this->t('Cadence', 'Cadence'), $this->t('Product design for a scheduling app.', 'Thiết kế sản phẩm cho một ứng dụng đặt lịch.')],
                        [$this->t('Harbor', 'Harbor'), $this->t('Design system for a fintech dashboard.', 'Hệ thống thiết kế cho một bảng điều khiển fintech.')],
                    ]),
                    $this->introBand(
                        $this->t('What I do', 'Tôi làm gì'),
                        $this->t('A compact set of skills, applied end to end.', 'Một bộ kỹ năng gọn gàng, áp dụng xuyên suốt từ đầu đến cuối.')),
                    $this->valueStripNode([
                        [$this->t('Product design', 'Thiết kế sản phẩm'), $this->t('Flows, UI and design systems.', 'Luồng, giao diện và hệ thống thiết kế.')],
                        [$this->t('Front-end', 'Front-end'), $this->t('Accessible, semantic markup.', 'Mã nguồn ngữ nghĩa, dễ tiếp cận.')],
                        [$this->t('Brand', 'Thương hiệu'), $this->t('Identity and art direction.', 'Nhận diện và định hướng mỹ thuật.')],
                    ]),
                    $this->pageTestimonialsNode([
                        [$this->t('A genuine collaborator — thoughtful, fast, and easy to work with from brief to launch.', 'Một người cộng sự thực thụ — chu đáo, nhanh nhẹn và dễ làm việc cùng từ lúc nhận đề bài đến khi ra mắt.'), 'Elena Ross', $this->t('Founder, Fieldnote', 'Nhà sáng lập, Fieldnote')],
                    ]),
                    $this->aboutNode(
                        $this->t('About', 'Giới thiệu'),
                        $this->t('I have spent the last decade helping teams ship thoughtful software. I care about clarity, accessibility and details that hold up over time. When I am not designing, I am usually writing about the craft.', 'Suốt một thập kỷ qua tôi đã đồng hành cùng các đội ngũ để tạo ra phần mềm chỉn chu. Tôi coi trọng sự rõ ràng, khả năng tiếp cận và những chi tiết bền vững theo thời gian. Khi không thiết kế, tôi thường viết về nghề.')),
                    $this->pageCtaNode(
                        $this->t('Let’s work together', 'Hãy cùng hợp tác'),
                        $this->t('Available for a small number of freelance and collaboration projects each quarter.', 'Tôi nhận một số lượng nhỏ dự án tự do và hợp tác mỗi quý.'),
                        $this->t('Get in touch', 'Liên hệ')),
                ]),
            // 5) BLOG — editorial magazine. Signature: lead story + reading-width bands.
            // hero,image-section,section,columns,section,columns(gallery),section(about),cta
            $this->page('blog-home', 'Blog Home', 'blog', ['blog', 'content', 'articles'],
                'An editorial blog landing page: a lead story, a featured piece, topic discovery, recent articles, an about note and a subscribe CTA.', [
                    $this->pageHeroNode(
                        $this->t('Field notes on building for the web', 'Ghi chép thực địa về chuyện dựng web'),
                        $this->t('Practical writing on design, front-end and shipping product — a couple of new pieces each month.', 'Những bài viết thiết thực về thiết kế, front-end và đưa sản phẩm ra mắt — một vài bài mới mỗi tháng.'),
                        $this->t('Read the latest', 'Đọc bài mới nhất'), $this->t('Browse archive', 'Xem kho lưu trữ'), 'plain'),
                    $this->mediaBandNode($this->t('Featured article illustration', 'Minh hoạ bài viết nổi bật'), $this->t('This week — Designing for the calm middle of a page', 'Tuần này — Thiết kế cho phần giữa điềm tĩnh của một trang')),
                    $this->introBand(
                        $this->t('Browse by topic', 'Duyệt theo chủ đề'),
                        $this->t('Start with the themes we write about most.', 'Bắt đầu với những chủ đề chúng tôi viết nhiều nhất.')),
                    $this->cardsNode([
                        [$this->t('Design', 'Thiết kế'), $this->t('Interface, systems and the decisions behind them.', 'Giao diện, hệ thống và những quyết định đằng sau chúng.')],
                        [$this->t('Engineering', 'Kỹ thuật'), $this->t('Server-rendered patterns and pragmatic front-end.', 'Các mẫu kết xuất phía máy chủ và front-end thực dụng.')],
                        [$this->t('Craft', 'Nghề'), $this->t('Process, taste and the details that add up.', 'Quy trình, gu thẩm mỹ và những chi tiết cộng dồn lại.')],
                    ], 'surface', 'md', '33'),
                    $this->introBand(
                        $this->t('Recent articles', 'Bài viết gần đây'),
                        $this->t('A few sample posts — edit these to feature your own writing.', 'Một vài bài mẫu — hãy chỉnh sửa để giới thiệu bài viết của riêng bạn.')),
                    $this->galleryNode([
                        [$this->t('Designing for the middle', 'Thiết kế cho phần giữa'), $this->t('Why the calm parts of a page matter as much as the loud ones.', 'Vì sao những phần điềm tĩnh của trang cũng quan trọng như những phần nổi bật.')],
                        [$this->t('Rendering on the server', 'Kết xuất phía máy chủ'), $this->t('A gentle case for HTML-first front-ends in 2026.', 'Một lập luận nhẹ nhàng cho front-end ưu tiên HTML trong năm 2026.')],
                        [$this->t('Editing without fear', 'Chỉnh sửa không còn e ngại'), $this->t('How reusable blocks keep a growing site consistent.', 'Cách các khối tái dùng giữ cho một website đang lớn luôn nhất quán.')],
                    ]),
                    $this->aboutNode(
                        $this->t('About this blog', 'Về blog này'),
                        $this->t('A small publication from a team that builds websites for a living. No trackers, no noise — just useful writing you can actually apply.', 'Một ấn phẩm nhỏ từ một đội ngũ sống bằng nghề dựng website. Không trình theo dõi, không nhiễu — chỉ là những bài viết hữu ích bạn có thể áp dụng được.')),
                    $this->pageCtaNode(
                        $this->t('Never miss a post', 'Đừng bỏ lỡ bài nào'),
                        $this->t('Add your email to get new articles when they publish. (Sample form — connect your own provider after import.)', 'Thêm email của bạn để nhận bài mới khi xuất bản. (Biểu mẫu mẫu — hãy kết nối nhà cung cấp của bạn sau khi nhập.)'),
                        $this->t('Subscribe', 'Đăng ký')),
                ]),
        ];
    }

    // ---- Page-private builders (PB-DEMO-DESIGN-R1) ---------------------------
    // Dedicated to the SIX Starter PAGES. They compose ONLY registered section/
    // block types with the shared theme tokens and the bounded surface/card + hero
    // style presets; no hardcoded colour, radius, shadow, raw CSS/class or markup
    // is emitted. Every human string is a canonical per-locale map
    // ({@see \Plugins\PageBuilder\Support\Localize}) so each page ships natural EN
    // + VI content. The curated SECTION helpers ({@see sections()}) and the shared
    // ctaNode/pricingCardsNode bodies are never touched here, so the nine curated
    // sections stay byte-identical.

    /** A canonical translatable-field value: an explicit per-locale map. */
    private function t(string $en, string $vi): array
    {
        return ['en' => $en, 'vi' => $vi];
    }

    /**
     * @param  array<string, string>  $text
     * @param  string|null  $size  optional bounded display-size variant (sm|md|lg|xl)
     */
    private function h(array $text, string $level, ?string $size = null): array
    {
        $data = ['text' => $text, 'level' => $level];
        if ($size !== null) {
            $data['size'] = $size;
        }

        return $this->block('heading', $data);
    }

    /** @param  array<string, string>  $text */
    private function p(array $text): array
    {
        return $this->block('text', ['text' => $text]);
    }

    /** @param  array<string, string>  $label */
    private function btn(array $label, string $style): array
    {
        return $this->block('button', ['label' => $label, 'url' => '#', 'style' => $style, 'size' => 'md']);
    }

    /** @param  array<string, string>  $alt */
    private function img(string $src, array $alt): array
    {
        return $this->block('image', ['src' => $src, 'alt' => $alt, 'caption' => '']);
    }

    /**
     * @param  array<string, string>  $quote
     * @param  array<string, string>  $role
     */
    private function qt(array $quote, string $author, array $role): array
    {
        return $this->block('quote', ['text' => $quote, 'author' => $author, 'role' => $role]);
    }

    /**
     * A page hero: a primary CTA, a REQUIRED secondary (outline) child button and
     * an optional supporting image. The hero title carries the page's single <h1>
     * intent (resolved by {@see \Plugins\PageBuilder\Support\HeadingRankResolver}).
     *
     * @param  array<string, string>  $title
     * @param  array<string, string>  $subtitle
     * @param  array<string, string>  $primaryLabel
     * @param  array<string, string>  $secondaryLabel
     * @param  array<string, string>|null  $imageAlt
     */
    private function pageHeroNode(array $title, array $subtitle, array $primaryLabel, array $secondaryLabel, string $style, ?string $image = null, ?array $imageAlt = null): array
    {
        $blocks = [$this->btn($secondaryLabel, 'outline')];
        if ($image !== null) {
            $blocks[] = $this->img($image, $imageAlt ?? $this->t('', ''));
        }

        return $this->sectionNode('hero', [
            'align' => 'center',
            'style' => $style,
            'width' => 'normal',
            'title' => $title,
            'subtitle' => $subtitle,
            'button_label' => $primaryLabel,
            'button_url' => '#',
        ], $blocks);
    }

    /**
     * A centered section-intro band: an <h2> heading plus a short lead paragraph.
     * Width/padding tune the page's vertical rhythm.
     *
     * @param  array<string, string>  $heading
     * @param  array<string, string>  $text
     */
    private function introBand(array $heading, array $text, string $width = 'normal', string $padding = 'md'): array
    {
        return $this->sectionNode('section', ['width' => $width, 'padding' => $padding, 'background' => ''], [
            $this->h($heading, 'h2'),
            $this->p($text),
        ]);
    }

    /**
     * A row of content cards (heading + text, optional action) on a surface/card
     * preset.
     *
     * @param  array<int, array{0: array<string,string>, 1: array<string,string>, 2?: array<string,string>}>  $cards
     */
    private function cardsNode(array $cards, string $surface, string $gap, string $width): array
    {
        $columns = [];
        foreach ($cards as $card) {
            $blocks = [$this->h($card[0], 'h3'), $this->p($card[1])];
            if (! empty($card[2])) {
                $blocks[] = $this->btn($card[2], 'link');
            }
            $columns[] = $this->columnBlock($width, $blocks, $surface);
        }

        return $this->sectionNode('columns', ['gap' => $gap], $columns);
    }

    /**
     * A quiet value strip: equal columns with a short <h3> label and one line of
     * text, no surface card — a deliberate change of rhythm from the card rows.
     *
     * @param  array<int, array{0: array<string,string>, 1: array<string,string>}>  $values
     */
    private function valueStripNode(array $values): array
    {
        $width = count($values) >= 4 ? '25' : '33';
        $columns = [];
        foreach ($values as [$label, $text]) {
            $columns[] = $this->columnBlock($width, [$this->h($label, 'h3'), $this->p($text)]);
        }

        return $this->sectionNode('columns', ['gap' => 'md'], $columns);
    }

    /**
     * A captioned media gallery (image + heading + caption cards). Every image is a
     * safe, locally packaged placeholder with alt text.
     *
     * @param  array<int, array{0: array<string,string>, 1: array<string,string>}>  $items
     */
    private function galleryNode(array $items): array
    {
        $columns = [];
        foreach ($items as [$title, $caption]) {
            $columns[] = $this->columnBlock('33', [
                $this->img(StarterAssets::IMAGE_PLACEHOLDER, $title),
                $this->h($title, 'h3'),
                $this->p($caption),
            ], 'surface');
        }

        return $this->sectionNode('columns', ['gap' => 'lg'], $columns);
    }

    /**
     * A single captioned media band (image-section) on its own full-width row.
     *
     * @param  array<string, string>  $alt
     * @param  array<string, string>  $caption
     */
    private function mediaBandNode(array $alt, array $caption): array
    {
        // image-section renders its own <figure> (image_url + caption); it needs no
        // child image block — a child would render a second, caption-less duplicate.
        return $this->sectionNode('image-section', [
            'align' => 'center',
            'image_url' => StarterAssets::IMAGE_PLACEHOLDER,
            'alt' => $alt,
            'caption' => $caption,
        ]);
    }

    /**
     * A calm about/editorial band: an <h2> plus a longer paragraph at reading width.
     *
     * @param  array<string, string>  $heading
     * @param  array<string, string>  $text
     */
    private function aboutNode(array $heading, array $text): array
    {
        return $this->sectionNode('section', ['width' => 'narrow', 'padding' => 'lg', 'background' => ''], [
            $this->h($heading, 'h2'),
            $this->p($text),
        ]);
    }

    /**
     * A page-owned testimonial band (distinct quotes per page). Author names are
     * proper nouns (identical in both locales); the role is localized.
     *
     * @param  array<int, array{0: array<string,string>, 1: string, 2: array<string,string>}>  $quotes
     */
    private function pageTestimonialsNode(array $quotes): array
    {
        $blocks = [];
        foreach ($quotes as [$quote, $author, $role]) {
            $blocks[] = $this->qt($quote, $author, $role);
        }

        return $this->sectionNode('testimonial', [], $blocks);
    }

    /**
     * A page-owned logo strip (four neutral marks under a short heading).
     *
     * @param  array<string, string>  $heading
     */
    private function pageLogoStripNode(array $heading): array
    {
        return $this->sectionNode('logo-strip', ['heading' => $heading], [
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Acme'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Globex'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Initech'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Umbrella'),
        ]);
    }

    /**
     * A page-owned closing CTA band — a `cta` section built page-privately so the
     * shared {@see ctaNode()} body (used by the curated cta-banner section) is never
     * touched.
     *
     * @param  array<string, string>  $title
     * @param  array<string, string>  $text
     * @param  array<string, string>  $buttonLabel
     */
    private function pageCtaNode(array $title, array $text, array $buttonLabel): array
    {
        return $this->sectionNode('cta', [
            'align' => 'center',
            'title' => $title,
            'text' => $text,
            'button_label' => $buttonLabel,
            'button_url' => '#',
        ]);
    }

    /**
     * A page-owned three-tier pricing row with a highlighted recommended plan (card
     * preset + primary CTA); the flanking tiers use surface + outline. Page-private
     * so the shared curated `pricing` section ({@see pricingCardsNode()}) stays
     * byte-identical.
     *
     * @param  array<int, array{0: array<string,string>, 1: array<string,string>, 2: array<int, array<string,string>>, 3: array<string,string>, 4: string, 5: string}>  $tiers
     */
    private function pagePricingNode(array $tiers): array
    {
        $columns = [];
        foreach ($tiers as [$name, $price, $features, $cta, $buttonStyle, $surface]) {
            $blocks = [$this->h($name, 'h3'), $this->p($price)];
            foreach ($features as $feature) {
                $blocks[] = $this->p($feature);
            }
            $blocks[] = $this->btn($cta, $buttonStyle);
            $columns[] = $this->columnBlock('33', $blocks, $surface);
        }

        return $this->sectionNode('columns', ['gap' => 'lg'], $columns);
    }

    /**
     * A page-owned FAQ band (page-private; there is no curated faq section).
     *
     * @param  array<string, string>  $heading
     * @param  array<int, array{0: array<string,string>, 1: array<string,string>}>  $items
     */
    private function pageFaqNode(array $heading, array $items): array
    {
        $blocks = [];
        foreach ($items as [$question, $answer]) {
            $blocks[] = $this->block('accordion-item', ['question' => $question, 'answer' => $answer]);
        }

        return $this->sectionNode('faq', ['heading' => $heading], $blocks);
    }

    // ---- Agency page composition (PB-DEMO-DESIGN-R1-A) -----------------------
    // Agency-only builders for the premium editorial redesign. They compose ONLY
    // registered section/block types through the generic B1 contracts (media/split
    // hero, bounded `cobalt` accent, eyebrow/lead text variants, safe art-directed
    // media). The shared page builders ABOVE are never touched, so the other five
    // Pages stay byte-identical.

    /** The single cohesive Agency accent, carried through the whole page subtree. */
    private const AGENCY_ACCENT = 'cobalt';

    /** An accent eyebrow kicker (text block, `eyebrow` variant). @param array<string,string> $text */
    private function eyebrow(array $text): array
    {
        return $this->block('text', ['text' => $text, 'variant' => 'eyebrow']);
    }

    /** A lead paragraph (text block, `lead` variant). @param array<string,string> $text */
    private function lead(array $text): array
    {
        return $this->block('text', ['text' => $text, 'variant' => 'lead']);
    }

    /** A 16:9 rounded case-study image on the art-directed media set. @param array<string,string> $alt */
    private function caseImage(string $src, array $alt): array
    {
        return $this->block('image', ['src' => $src, 'alt' => $alt, 'caption' => '', 'ratio' => '16x9', 'style' => 'rounded']);
    }

    /** A decorative bounded icon from the plugin IconLibrary (capability/process). */
    private function iconBlock(string $name): array
    {
        return $this->block('icon', ['name' => $name, 'label' => '']);
    }

    /**
     * The media-anchored split hero: an accent eyebrow, the single display <h1>, a
     * readable subtitle, a strong primary + subordinate secondary action, and the
     * editorial studio artwork beside the content.
     *
     * @param  array<string, string>  $eyebrow
     * @param  array<string, string>  $title
     * @param  array<string, string>  $subtitle
     * @param  array<string, string>  $primaryLabel
     * @param  array<string, string>  $secondaryLabel
     */
    private function agencyHeroNode(array $eyebrow, array $title, array $subtitle, array $primaryLabel, array $secondaryLabel): array
    {
        return $this->sectionNode('hero', [
            'eyebrow' => $eyebrow,
            'title' => $title,
            'subtitle' => $subtitle,
            'button_label' => $primaryLabel,
            'button_url' => '#',
            'style' => 'plain',
            'align' => 'left',
            'width' => 'wide',
            'layout' => 'split',
            'media_position' => 'end',
            'media_shape' => 'shadow',
            'image_url' => StarterAssets::MEDIA_HERO_STUDIO,
            'image_alt' => $this->t('Abstract studio composition in cobalt and ink', 'Bố cục studio trừu tượng với sắc xanh cobalt và mực đậm'),
            'accent' => self::AGENCY_ACCENT,
        ], [$this->btn($secondaryLabel, 'outline')]);
    }

    /**
     * A quiet proof strip: bounded studio metrics (a number + an eyebrow label), no
     * cards — immediate, truthful credibility right under the hero.
     *
     * @param  array<int, array{0: array<string,string>, 1: array<string,string>}>  $metrics
     */
    private function agencyMetricsNode(array $metrics): array
    {
        $columns = [];
        foreach ($metrics as [$value, $label]) {
            $columns[] = $this->columnBlock('33', [$this->h($value, 'h3', 'xl'), $this->eyebrow($label)]);
        }

        return $this->sectionNode('columns', ['gap' => 'lg', 'accent' => self::AGENCY_ACCENT], $columns);
    }

    /**
     * A section intro with an accent eyebrow kicker, an <h2> heading and a lead line.
     *
     * @param  array<string, string>  $eyebrow
     * @param  array<string, string>  $heading
     * @param  array<string, string>  $text
     */
    private function agencyIntroNode(array $eyebrow, array $heading, array $text, ?string $tone = null): array
    {
        $data = ['width' => 'normal', 'padding' => $tone === null ? 'md' : 'lg', 'background' => '', 'accent' => self::AGENCY_ACCENT];
        if ($tone !== null) {
            $data['tone'] = $tone;
        }

        return $this->sectionNode('section', $data, [
            $this->eyebrow($eyebrow),
            $this->h($heading, 'h2', 'xl'),
            $this->lead($text),
        ]);
    }

    /**
     * Image-led selected work: three distinct case cards (art-directed tile +
     * category eyebrow + project <h3> + outcome + a case action).
     *
     * @param  array<int, array{0: string, 1: array<string,string>, 2: array<string,string>, 3: array<string,string>, 4: array<string,string>}>  $cases
     */
    private function agencyCaseCardsNode(array $cases): array
    {
        $columns = [];
        foreach ($cases as $i => [$image, $category, $name, $outcome, $action]) {
            // Index 0 is the flagship (larger title); the featured grid variant gives
            // it stronger visual weight so the three cases no longer read as one block.
            $columns[] = $this->columnBlock('33', [
                $this->caseImage($image, $name),
                $this->eyebrow($category),
                $this->h($name, 'h3', $i === 0 ? 'lg' : 'md'),
                $this->p($outcome),
                $this->btn($action, 'link'),
            ], 'card');
        }

        return $this->sectionNode('columns', ['gap' => 'lg', 'variant' => 'featured', 'accent' => self::AGENCY_ACCENT], $columns);
    }

    /**
     * Capabilities: differentiated service groups as open (un-carded) columns — a
     * deliberate change of rhythm from the work cards.
     *
     * @param  array<int, array{0: string, 1: array<string,string>, 2: array<string,string>}>  $caps  [icon, title, text]
     */
    private function agencyCapabilitiesNode(array $caps): array
    {
        $columns = [];
        foreach ($caps as [$icon, $name, $text]) {
            $columns[] = $this->columnBlock('33', [$this->iconBlock($icon), $this->h($name, 'h3', 'md'), $this->p($text)], 'card');
        }

        return $this->sectionNode('columns', ['gap' => 'lg', 'variant' => 'cards', 'accent' => self::AGENCY_ACCENT, 'tone' => 'neutral'], $columns);
    }

    /**
     * A credible numbered process: four steps, each an accent number eyebrow, an
     * <h3> activity and a real one-line description.
     *
     * @param  array<int, array{0: string, 1: array<string,string>, 2: array<string,string>}>  $steps  [icon, title, desc]
     */
    private function agencyProcessNode(array $steps): array
    {
        // The step number is drawn by the timeline variant (CSS counter in DOM order),
        // so each step carries only its icon, title and description.
        $columns = [];
        foreach ($steps as [$icon, $title, $desc]) {
            $columns[] = $this->columnBlock('25', [$this->iconBlock($icon), $this->h($title, 'h3', 'md'), $this->p($desc)]);
        }

        return $this->sectionNode('columns', ['gap' => 'lg', 'variant' => 'timeline', 'accent' => self::AGENCY_ACCENT, 'tone' => 'ink'], $columns);
    }

    /**
     * One coherent, credible testimonial — the section's own pull-quote (not a
     * staggered stack), with a demo-safe person + role.
     *
     * @param  array<string, string>  $quote
     * @param  array<string, string>  $role
     */
    private function agencyTestimonialNode(array $quote, string $author, array $role): array
    {
        return $this->sectionNode('testimonial', [
            'quote' => $quote,
            'author' => $author,
            'role' => $role,
            'variant' => 'feature',
            'accent' => self::AGENCY_ACCENT,
            'tone' => 'tint',
        ]);
    }

    /**
     * Intentional partner wordmarks on the art-directed mark set (credible, complete
     * logos rendered at full ink — never skeleton placeholders).
     *
     * @param  array<string, string>  $heading
     */
    private function agencyMarksNode(array $heading): array
    {
        return $this->sectionNode('logo-strip', ['heading' => $heading, 'grayscale' => false, 'tone' => 'neutral'], [
            $this->logoBlock(StarterAssets::MEDIA_MARK_01, 'Northwind'),
            $this->logoBlock(StarterAssets::MEDIA_MARK_02, 'Lumen'),
            $this->logoBlock(StarterAssets::MEDIA_MARK_03, 'Brightpath'),
            $this->logoBlock(StarterAssets::MEDIA_MARK_04, 'Meridian'),
        ]);
    }

    /**
     * The closing enquiry CTA with the Agency accent (a page-private `cta` section, so
     * the shared ctaNode body stays byte-identical).
     *
     * @param  array<string, string>  $title
     * @param  array<string, string>  $text
     * @param  array<string, string>  $buttonLabel
     */
    private function agencyCtaNode(array $title, array $text, array $buttonLabel): array
    {
        return $this->sectionNode('cta', [
            'align' => 'center',
            'title' => $title,
            'text' => $text,
            'button_label' => $buttonLabel,
            'button_url' => '#',
            'accent' => self::AGENCY_ACCENT,
            'tone' => 'ink',
        ]);
    }

    // ---- Sections ------------------------------------------------------------

    /**
     * @return array<int, DesignResource>
     */
    public function sections(): array
    {
        return [
            $this->section('hero-modern', 'Modern Hero', 'hero', ['hero', 'dark', 'modern'],
                'A bold, dark hero with a headline, supporting line and primary action.',
                $this->heroNode('Design that works everywhere', 'A modern hero band with a strong headline and a single, clear call to action.', 'Get started', '#', 'dark')),
            $this->section('hero-company', 'Company Hero', 'hero', ['hero', 'business', 'surface'],
                'A calm corporate hero on a soft surface background.',
                $this->heroNode('Build better products faster', 'A confident corporate hero that introduces your company and its promise.', 'Learn more', '#', 'surface')),
            $this->section('hero-saas', 'SaaS Hero', 'hero', ['hero', 'saas', 'product'],
                'A product hero pairing a headline with a screenshot.',
                $this->heroNode('Everything you need to launch', 'A product-led hero with a headline, subtitle, action and a supporting image.', 'Start free', '#', 'plain', StarterAssets::IMAGE_PLACEHOLDER, 'Product screenshot')),
            $this->section('feature-grid', 'Feature Grid', 'features', ['features', 'grid', 'columns'],
                'A three-column grid of concise feature highlights.',
                $this->featureGridNode([
                    ['Fast', 'Rendered on the server for speed.'],
                    ['Flexible', 'Compose pages from reusable blocks.'],
                    ['Safe', 'Output is escaped by default.'],
                ])),
            $this->section('feature-cards', 'Feature Cards', 'features', ['features', 'cards', 'columns'],
                'A row of feature cards, each with a heading, text and action.',
                $this->featureCardsNode([
                    ['Strategy', 'Positioning and research that sets direction.', 'Learn more'],
                    ['Design', 'Interfaces with real character.', 'Learn more'],
                    ['Build', 'Fast, accessible delivery.', 'Learn more'],
                ])),
            $this->section('testimonials', 'Testimonials', 'social-proof', ['testimonials', 'quotes', 'social-proof'],
                'Three customer testimonials laid out as clean, equal cards.',
                $this->testimonialCardsNode()),
            $this->section('logo-strip', 'Logo Strip', 'social-proof', ['logos', 'brands', 'social-proof'],
                'A row of client or partner logos under a short, reassuring heading.',
                $this->logoStripSectionNode()),
            $this->section('cta-banner', 'CTA Banner', 'cta', ['cta', 'conversion', 'banner'],
                'A focused call-to-action band with a headline and button.',
                $this->ctaNode('Ready to get started?', 'Create your first page in minutes — no code required.', 'Get started')),
            $this->section('pricing', 'Pricing', 'conversion', ['pricing', 'plans', 'conversion'],
                'A three-tier pricing row with a highlighted recommended plan.',
                $this->pricingCardsNode()),
        ];
    }

    // ---- Resource builders ---------------------------------------------------

    /**
     * @param  array<int, string>  $tags
     * @param  array<int, array<string, mixed>>  $sectionNodes
     */
    private function page(string $id, string $name, string $category, array $tags, string $description, array $sectionNodes): DesignResource
    {
        return new DesignResource(
            rid: self::SOURCE.':'.Rid::KIND_PAGE.':'.$id,
            source: self::SOURCE,
            kind: Rid::KIND_PAGE,
            resourceId: $id,
            version: self::VERSION,
            name: $name,
            category: $category,
            tags: $tags,
            payload: $this->document($sectionNodes),
            description: $description,
            preview: $this->previewFor($id, Rid::KIND_PAGE),
            compatibility: ['schemaVersion' => '1'],
            distribution: ['entitlement' => 'free'],
            localized: $this->localizedMetadata($id, $name, $description),
        );
    }

    /**
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>  $sectionNode
     */
    private function section(string $id, string $name, string $category, array $tags, string $description, array $sectionNode): DesignResource
    {
        return new DesignResource(
            rid: self::SOURCE.':'.Rid::KIND_SECTION.':'.$id,
            source: self::SOURCE,
            kind: Rid::KIND_SECTION,
            resourceId: $id,
            version: self::VERSION,
            name: $name,
            category: $category,
            tags: $tags,
            payload: $this->document([$sectionNode]),
            description: $description,
            preview: $this->previewFor($id, Rid::KIND_SECTION),
            compatibility: ['schemaVersion' => '1'],
            distribution: ['entitlement' => 'free'],
            localized: $this->localizedMetadata($id, $name, $description),
        );
    }

    /**
     * Build the resource's per-field locale maps (PB-FREE-LIBRARY-DESIGN-1-F). English
     * is the inline invariant (single source); Vietnamese comes from {@see VI_METADATA}.
     * Both locales are set explicitly so a visible resource never depends on EN fallback
     * when the Admin UI is Vietnamese. Only the human presentation fields are localized.
     *
     * @return array{name: array<string, string>, description: array<string, string>}
     */
    private function localizedMetadata(string $id, string $name, string $description): array
    {
        $vi = self::VI_METADATA[$id] ?? null;

        return [
            'name' => ['en' => $name, 'vi' => $vi['name'] ?? $name],
            'description' => ['en' => $description, 'vi' => $vi['description'] ?? $description],
        ];
    }

    /**
     * The catalog preview descriptor (PB-FREE-LIBRARY-DESIGN-1-F). Points the card at
     * the real, deterministically-generated thumbnail shipped for this resource. The
     * former `sampleContent` flag is gone: the catalog thumbnail is now derived from the
     * resource's canonical payload, so Preview never depicts content absent from Apply.
     *
     * @return array<string, mixed>
     */
    private function previewFor(string $id, string $kind): array
    {
        return [
            'device' => 'desktop',
            'thumbnail' => StarterAssets::thumbnailUrl($kind, $id),
        ];
    }

    // ---- Layout node builders (registered types only) ------------------------

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{schema: string, sections: array<int, array<string, mixed>>}
     */
    private function document(array $sections): array
    {
        return ['schema' => LayoutNormalizer::SCHEMA, 'sections' => $sections];
    }

    /**
     * @return array<string, mixed>
     */
    private function heroNode(string $title, string $subtitle, string $buttonLabel, string $buttonUrl, string $style = 'plain', ?string $image = null, ?string $imageAlt = null): array
    {
        $blocks = [];
        if ($image !== null) {
            $blocks[] = $this->imageBlock($image, $imageAlt ?? '');
        }

        return $this->sectionNode('hero', [
            'align' => 'center',
            'style' => $style,
            'width' => 'normal',
            'title' => $title,
            'subtitle' => $subtitle,
            'button_label' => $buttonLabel,
            'button_url' => $buttonUrl,
        ], $blocks);
    }

    private function ctaNode(string $title, string $text, string $buttonLabel, string $buttonUrl = '#'): array
    {
        return $this->sectionNode('cta', [
            'align' => 'center',
            'title' => $title,
            'text' => $text,
            'button_label' => $buttonLabel,
            'button_url' => $buttonUrl,
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $features
     */
    private function featureGridNode(array $features): array
    {
        $columns = [];
        foreach ($features as [$title, $text]) {
            $columns[] = $this->columnBlock('33', [
                $this->headingBlock($title, 'h3'),
                $this->textBlock($text),
            ]);
        }

        return $this->sectionNode('columns', ['gap' => 'md'], $columns);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $cards
     */
    private function featureCardsNode(array $cards): array
    {
        $columns = [];
        foreach ($cards as [$title, $text, $action]) {
            $columns[] = $this->columnBlock('33', [
                $this->headingBlock($title, 'h3'),
                $this->textBlock($text),
                $this->buttonBlock($action, '#', 'outline'),
            ]);
        }

        return $this->sectionNode('columns', ['gap' => 'lg'], $columns);
    }

    private function pricingNode(): array
    {
        $tiers = [
            ['Starter', 'For small sites getting off the ground.', 'Choose Starter'],
            ['Pro', 'For growing teams that need more room.', 'Choose Pro'],
            ['Scale', 'For large organisations with advanced needs.', 'Contact sales'],
        ];

        $columns = [];
        foreach ($tiers as [$name, $text, $action]) {
            $columns[] = $this->columnBlock('33', [
                $this->headingBlock($name, 'h3'),
                $this->textBlock($text),
                $this->buttonBlock($action, '#', 'primary'),
            ]);
        }

        return $this->sectionNode('columns', ['gap' => 'md'], $columns);
    }

    private function testimonialsNode(): array
    {
        return $this->sectionNode('testimonial', [], [
            $this->quoteBlock('This made our redesign effortless.', 'Alex Rivera', 'Product Lead'),
            $this->quoteBlock('We shipped in days, not weeks.', 'Jordan Lee', 'Founder'),
        ]);
    }

    private function logoStripNode(string $heading): array
    {
        return $this->sectionNode('logo-strip', ['heading' => $heading], [
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Partner one'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Partner two'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Partner three'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Partner four'),
        ]);
    }

    // ---- Batch-D curated section nodes (PB-FREE-LIBRARY-DESIGN-1-D) -----------
    // These are dedicated to the curated SECTIONS. The starter PAGES keep their
    // own shared nodes ({@see testimonialsNode()}/{@see logoStripNode()}/
    // {@see pricingNode()}) unchanged, so Batch-E pages render byte-equivalently.

    /**
     * Three equal customer testimonials as clean surface cards (columns + the
     * token-based `surface` preset), replacing the single stacked pull-quote.
     */
    private function testimonialCardsNode(): array
    {
        $card = fn (string $quote, string $author, string $role): array => $this->columnBlock('33', [
            $this->quoteBlock($quote, $author, $role),
        ], 'surface');

        return $this->sectionNode('columns', ['gap' => 'lg'], [
            $card('This made our redesign effortless — we launched a new marketing site in a week.', 'Alex Rivera', 'Product Lead, Lumen'),
            $card('The block library keeps everything consistent. Non-designers still ship on-brand pages.', 'Jordan Lee', 'Founder, Brightpath'),
            $card('Routine page updates no longer wait on engineering. It paid for itself in a month.', 'Sam Devi', 'Head of Growth, Northwind'),
        ]);
    }

    /** A fuller, reassuring logo strip for the curated section (five marks). */
    private function logoStripSectionNode(): array
    {
        return $this->sectionNode('logo-strip', ['heading' => 'Trusted by teams at fast-growing companies'], [
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Acme'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Globex'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Umbrella'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Initech'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Hooli'),
        ]);
    }

    /**
     * Three pricing tiers as cards with a highlighted recommended plan. The
     * middle tier uses the elevated `card` preset + a primary CTA; the flanking
     * tiers use the subtle `surface` preset + outline CTAs for clear hierarchy.
     */
    private function pricingCardsNode(): array
    {
        $tier = function (string $name, string $price, string $period, array $features, string $cta, string $buttonStyle, string $surface): array {
            $blocks = [
                $this->headingBlock($name, 'h3'),
                $this->textBlock(trim($price.' '.$period)),
            ];
            foreach ($features as $feature) {
                $blocks[] = $this->textBlock($feature);
            }
            $blocks[] = $this->buttonBlock($cta, '#', $buttonStyle);

            return $this->columnBlock('33', $blocks, $surface);
        };

        return $this->sectionNode('columns', ['gap' => 'lg'], [
            $tier('Starter', '$0', '/ forever', ['1 project', 'Community support', 'Starter blocks'], 'Get started', 'outline', 'surface'),
            $tier('Pro', '$29', '/ month', ['Unlimited projects', 'Priority support', 'All blocks & patterns'], 'Start free trial', 'primary', 'card'),
            $tier('Scale', 'Custom', '', ['SSO & roles', 'Dedicated support', 'SLA & onboarding'], 'Contact sales', 'outline', 'surface'),
        ]);
    }

    // ---- Primitive node helpers ----------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function sectionNode(string $type, array $data, array $blocks = []): array
    {
        return ['type' => $type, 'id' => null, 'data' => $data, 'blocks' => $blocks];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function block(string $type, array $data): array
    {
        return ['type' => $type, 'data' => $data];
    }

    private function headingBlock(string $text, string $level): array
    {
        return $this->block('heading', ['text' => $text, 'level' => $level]);
    }

    private function textBlock(string $text): array
    {
        return $this->block('text', ['text' => $text]);
    }

    private function buttonBlock(string $label, string $url, string $style): array
    {
        return $this->block('button', ['label' => $label, 'url' => $url, 'style' => $style, 'size' => 'md']);
    }

    private function imageBlock(string $src, string $alt): array
    {
        return $this->block('image', ['src' => $src, 'alt' => $alt, 'caption' => '']);
    }

    private function quoteBlock(string $text, string $author, string $role): array
    {
        return $this->block('quote', ['text' => $text, 'author' => $author, 'role' => $role]);
    }

    private function logoBlock(string $src, string $alt): array
    {
        return $this->block('logo', ['src' => $src, 'alt' => $alt]);
    }

    /**
     * A `column` block carries its nested blocks under `data.blocks` (the frozen
     * container shape the renderer and import both understand).
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function columnBlock(string $width, array $blocks, ?string $surface = null): array
    {
        $data = ['width' => $width, 'blocks' => $blocks];
        if ($surface !== null) {
            $data['surface'] = $surface;
        }

        return $this->block('column', $data);
    }
}
