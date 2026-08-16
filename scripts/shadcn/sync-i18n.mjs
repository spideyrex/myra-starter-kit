/**
 * Writes the "blocks" i18n namespace into en/ms/zh from the committed manifest.
 *
 *   node scripts/shadcn/sync-i18n.mjs
 *
 * One entry per manifest id in all three locales, inserted immediately after
 * "charts" so the hunk never overlaps another bundle's namespace. A block whose
 * description has no translation FAILS here rather than shipping English into
 * ms.json and zh.json.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..', '..');
const sources = JSON.parse(fs.readFileSync(path.join(here, '_sources.json'), 'utf8'));
const manifest = JSON.parse(fs.readFileSync(path.join(root, sources.manifest), 'utf8'));

const LOCALES = ['en', 'ms', 'zh'];
const AFTER_KEY = 'charts';

/** Upstream copy-pastes its chart descriptions (ChartBarDefault says "a line chart"). */
const DESCRIPTION_OVERRIDES = {
    'sidebar-demo': 'The sidebar primitive on its own, with no page chrome.',
    'products-01': 'A products table with filters, pagination and row actions.',
    ChartAreaAxes: 'An area chart with visible axes.',
    ChartAreaGradient: 'An area chart with a gradient fill.',
    ChartAreaIcons: 'An area chart with icons in the legend.',
    ChartAreaInteractive: 'An interactive area chart with a range selector.',
    ChartBarDefault: 'A bar chart in its default configuration.',
    ChartBarHorizontal: 'A horizontal bar chart.',
    ChartBarInteractive: 'An interactive bar chart with a series toggle.',
    ChartBarMultiple: 'A bar chart with multiple series.',
    ChartLineDefault: 'A line chart in its default configuration.',
    ChartLineInteractive: 'An interactive line chart with a series toggle.',
    ChartLineLinear: 'A line chart with linear interpolation.',
    ChartLineStep: 'A line chart with stepped interpolation.',
    ChartPieDonut: 'A donut chart.',
    ChartPieDonutText: 'A donut chart with a total in the centre.',
    ChartPieSimple: 'A simple pie chart.',
    ChartPieStacked: 'A pie chart with stacked sections.',
    ChartTooltipDefault: 'A chart tooltip in its default configuration.',
    ChartTooltipIcons: 'A chart tooltip with series icons.',
    ChartTooltipIndicatorLine: 'A chart tooltip with a line indicator.',
    ChartTooltipIndicatorNone: 'A chart tooltip with no indicator.',
    ChartTooltipLabelCustom: 'A chart tooltip with a custom label.',
    ChartTooltipLabelFormatter: 'A chart tooltip with a formatted label.',
    ChartTooltipLabelNone: 'A chart tooltip with the label hidden.',
};

const PHRASES = {
    'A simple sidebar with navigation grouped by section.': ['Bar sisi ringkas dengan navigasi dikumpulkan mengikut bahagian.', '导航按区块分组的简洁侧边栏。'],
    'A sidebar with collapsible sections.': ['Bar sisi dengan bahagian boleh dikuncupkan.', '带可折叠分区的侧边栏。'],
    'A sidebar with submenus.': ['Bar sisi dengan submenu.', '带子菜单的侧边栏。'],
    'A floating sidebar with submenus.': ['Bar sisi terapung dengan submenu.', '带子菜单的浮动侧边栏。'],
    'A sidebar with collapsible submenus.': ['Bar sisi dengan submenu boleh dikuncupkan.', '带可折叠子菜单的侧边栏。'],
    'A sidebar with submenus as dropdowns.': ['Bar sisi dengan submenu sebagai menu lungsur.', '子菜单以下拉形式呈现的侧边栏。'],
    'A sidebar that collapses to icons': ['Bar sisi yang menguncup menjadi ikon.', '可折叠为图标的侧边栏。'],
    'An inset sidebar with secondary navigation.': ['Bar sisi terbenam dengan navigasi sekunder.', '带次级导航的内嵌侧边栏。'],
    'Collapsible nested sidebars.': ['Bar sisi bersarang yang boleh dikuncupkan.', '可折叠的嵌套侧边栏。'],
    'A sidebar in a popover.': ['Bar sisi di dalam popover.', '位于弹出层中的侧边栏。'],
    'A sidebar with a collapsible file tree.': ['Bar sisi dengan pepohon fail boleh dikuncupkan.', '带可折叠文件树的侧边栏。'],
    'A sidebar with a calendar.': ['Bar sisi dengan kalendar.', '带日历的侧边栏。'],
    'A sidebar in a dialog.': ['Bar sisi di dalam dialog.', '位于对话框中的侧边栏。'],
    'A sidebar on the right.': ['Bar sisi di sebelah kanan.', '位于右侧的侧边栏。'],
    'A left and right sidebar.': ['Bar sisi kiri dan kanan.', '左右双侧边栏。'],
    'A sidebar with a sticky site header.': ['Bar sisi dengan pengepala tapak melekat.', '带吸顶站点页眉的侧边栏。'],
    'The sidebar primitive on its own, with no page chrome.': ['Primitif bar sisi bersendirian, tanpa kelengkapan halaman.', '不含页面外框的侧边栏原语。'],
    'A dashboard with sidebar, charts and data table.': ['Papan pemuka dengan bar sisi, carta dan jadual data.', '含侧边栏、图表与数据表的仪表板。'],
    'A products table with filters, pagination and row actions.': ['Jadual produk dengan penapis, penomboran dan tindakan baris.', '含筛选、分页与行操作的产品表格。'],
    'A simple login form.': ['Borang log masuk ringkas.', '简洁登录表单。'],
    'A two column login page with a cover image.': ['Halaman log masuk dua lajur dengan imej muka depan.', '带封面图的双栏登录页。'],
    'A login page with a muted background color.': ['Halaman log masuk dengan warna latar lembut.', '使用柔和背景色的登录页。'],
    'A login page with form and image.': ['Halaman log masuk dengan borang dan imej.', '含表单与图片的登录页。'],
    'A simple email-only login page.': ['Halaman log masuk ringkas e-mel sahaja.', '仅使用邮箱的简洁登录页。'],
    'A simple signup form.': ['Borang pendaftaran ringkas.', '简洁注册表单。'],
    'A two column signup page with a cover image.': ['Halaman pendaftaran dua lajur dengan imej muka depan.', '带封面图的双栏注册页。'],
    'A signup page with a muted background color.': ['Halaman pendaftaran dengan warna latar lembut.', '使用柔和背景色的注册页。'],
    'A signup page with form and image.': ['Halaman pendaftaran dengan borang dan imej.', '含表单与图片的注册页。'],
    'A simple signup form with social providers.': ['Borang pendaftaran ringkas dengan pembekal sosial.', '含社交登录方式的简洁注册表单。'],
    'A simple OTP verification form.': ['Borang pengesahan OTP ringkas.', '简洁 OTP 验证表单。'],
    'A two column OTP page with a cover image.': ['Halaman OTP dua lajur dengan imej muka depan.', '带封面图的双栏 OTP 页面。'],
    'An OTP page with a muted background color.': ['Halaman OTP dengan warna latar lembut.', '使用柔和背景色的 OTP 页面。'],
    'An OTP page with form and image.': ['Halaman OTP dengan borang dan imej.', '含表单与图片的 OTP 页面。'],
    'A simple OTP form with social providers.': ['Borang OTP ringkas dengan pembekal sosial.', '含社交登录方式的简洁 OTP 表单。'],
    'An area chart with visible axes.': ['Carta kawasan dengan paksi kelihatan.', '带可见坐标轴的面积图。'],
    'An area chart with a gradient fill.': ['Carta kawasan dengan isian kecerunan.', '带渐变填充的面积图。'],
    'An area chart with icons in the legend.': ['Carta kawasan dengan ikon pada legenda.', '图例中带图标的面积图。'],
    'An interactive area chart with a range selector.': ['Carta kawasan interaktif dengan pemilih julat.', '带范围选择器的交互式面积图。'],
    'A bar chart in its default configuration.': ['Carta bar dengan tetapan lalai.', '默认配置的柱状图。'],
    'A horizontal bar chart.': ['Carta bar mendatar.', '横向柱状图。'],
    'An interactive bar chart with a series toggle.': ['Carta bar interaktif dengan togol siri.', '带系列切换的交互式柱状图。'],
    'A bar chart with multiple series.': ['Carta bar dengan berbilang siri.', '含多个系列的柱状图。'],
    'A line chart in its default configuration.': ['Carta garis dengan tetapan lalai.', '默认配置的折线图。'],
    'An interactive line chart with a series toggle.': ['Carta garis interaktif dengan togol siri.', '带系列切换的交互式折线图。'],
    'A line chart with linear interpolation.': ['Carta garis dengan interpolasi linear.', '采用线性插值的折线图。'],
    'A line chart with stepped interpolation.': ['Carta garis dengan interpolasi berperingkat.', '采用阶梯插值的折线图。'],
    'A donut chart.': ['Carta donat.', '环形图。'],
    'A donut chart with a total in the centre.': ['Carta donat dengan jumlah di tengah.', '中心显示总计的环形图。'],
    'A simple pie chart.': ['Carta pai ringkas.', '简洁饼图。'],
    'A pie chart with stacked sections.': ['Carta pai dengan bahagian bertindan.', '带堆叠扇区的饼图。'],
    'A chart tooltip in its default configuration.': ['Tip alat carta dengan tetapan lalai.', '默认配置的图表提示框。'],
    'A chart tooltip with series icons.': ['Tip alat carta dengan ikon siri.', '带系列图标的图表提示框。'],
    'A chart tooltip with a line indicator.': ['Tip alat carta dengan penunjuk garis.', '带线条指示器的图表提示框。'],
    'A chart tooltip with no indicator.': ['Tip alat carta tanpa penunjuk.', '不含指示器的图表提示框。'],
    'A chart tooltip with a custom label.': ['Tip alat carta dengan label tersuai.', '带自定义标签的图表提示框。'],
    'A chart tooltip with a formatted label.': ['Tip alat carta dengan label berformat.', '带格式化标签的图表提示框。'],
    'A chart tooltip with the label hidden.': ['Tip alat carta dengan label disembunyikan.', '隐藏标签的图表提示框。'],
};

const STATIC = {
    en: {
        title: 'shadcn Blocks',
        description: 'Every shadcn-vue registry block, vendored verbatim and previewed in isolation.',
        search: 'Search blocks',
        allCategories: 'All',
        resultCount: '{n} blocks shown',
        noResults: 'No block matches that search.',
        view: 'Open block',
        categories: {
            sidebar: 'Sidebar', dashboard: 'Dashboard', login: 'Login', signup: 'Sign up',
            otp: 'One-time code', products: 'Products', chart: 'Charts', other: 'Other',
        },
        preview: {
            tab: 'Preview',
            frameTitle: 'Live preview of {name}',
            openInNewTab: 'Open preview in a new tab',
            viewport: 'Viewport',
            viewportFull: 'Full width',
            viewportPx: '{px} px',
            viewportAnnounced: 'Viewport: {size}',
            dark: 'Dark mode',
        },
        source: { tab: 'Code', copy: 'Copy', copied: 'Copied', file: 'Source of {path}' },
        dependencies: { tab: 'Dependencies', registry: 'Registry components', npm: 'npm packages', none: 'None' },
        unavailable: {
            title: 'Preview unavailable',
            body: 'This block needs dependencies this install does not ship ({deps}). Its source is vendored in full and shown below.',
            badge: 'Source only',
        },
        gaps: {
            title: 'Known gaps',
            calendar: 'No Vue calendar block exists upstream, so none is vendored.',
            charts: 'Chart blocks are vendored as source only: they need the unovis charting packages and the chart registry component, neither of which is installed here.',
        },
    },
    ms: {
        title: 'Blok shadcn',
        description: 'Setiap blok registri shadcn-vue, dibawa masuk secara verbatim dan dipratonton secara berasingan.',
        search: 'Cari blok',
        allCategories: 'Semua',
        resultCount: '{n} blok dipaparkan',
        noResults: 'Tiada blok sepadan dengan carian itu.',
        view: 'Buka blok',
        categories: {
            sidebar: 'Bar sisi', dashboard: 'Papan pemuka', login: 'Log masuk', signup: 'Pendaftaran',
            otp: 'Kod sekali guna', products: 'Produk', chart: 'Carta', other: 'Lain-lain',
        },
        preview: {
            tab: 'Pratonton',
            frameTitle: 'Pratonton langsung {name}',
            openInNewTab: 'Buka pratonton dalam tab baharu',
            viewport: 'Port pandang',
            viewportFull: 'Lebar penuh',
            viewportPx: '{px} px',
            viewportAnnounced: 'Port pandang: {size}',
            dark: 'Mod gelap',
        },
        source: { tab: 'Kod', copy: 'Salin', copied: 'Disalin', file: 'Sumber {path}' },
        dependencies: { tab: 'Kebergantungan', registry: 'Komponen registri', npm: 'Pakej npm', none: 'Tiada' },
        unavailable: {
            title: 'Pratonton tidak tersedia',
            body: 'Blok ini memerlukan kebergantungan yang tidak disertakan dalam pemasangan ini ({deps}). Sumbernya lengkap dan dipaparkan di bawah.',
            badge: 'Sumber sahaja',
        },
        gaps: {
            title: 'Jurang diketahui',
            calendar: 'Tiada blok kalendar Vue di hulu, jadi tiada yang dibawa masuk.',
            charts: 'Blok carta dibawa masuk sebagai sumber sahaja: ia memerlukan pakej carta unovis dan komponen registri carta, yang kedua-duanya tidak dipasang di sini.',
        },
    },
    zh: {
        title: 'shadcn 区块',
        description: 'shadcn-vue 注册表中的每个区块，原样引入并在隔离环境中预览。',
        search: '搜索区块',
        allCategories: '全部',
        resultCount: '显示 {n} 个区块',
        noResults: '没有匹配的区块。',
        view: '打开区块',
        categories: {
            sidebar: '侧边栏', dashboard: '仪表板', login: '登录', signup: '注册',
            otp: '一次性验证码', products: '产品', chart: '图表', other: '其他',
        },
        preview: {
            tab: '预览',
            frameTitle: '{name} 的实时预览',
            openInNewTab: '在新标签页中打开预览',
            viewport: '视口',
            viewportFull: '全宽',
            viewportPx: '{px} 像素',
            viewportAnnounced: '视口：{size}',
            dark: '深色模式',
        },
        source: { tab: '代码', copy: '复制', copied: '已复制', file: '{path} 的源码' },
        dependencies: { tab: '依赖', registry: '注册表组件', npm: 'npm 包', none: '无' },
        unavailable: {
            title: '预览不可用',
            body: '该区块需要本安装未提供的依赖（{deps}）。其源码已完整引入并显示在下方。',
            badge: '仅源码',
        },
        gaps: {
            title: '已知缺口',
            calendar: '上游没有 Vue 版日历区块，因此未引入。',
            charts: '图表区块仅引入源码：它们需要 unovis 图表包与 chart 注册表组件，本项目均未安装。',
        },
    },
};

function humanise(id) {
    if (/^[A-Z]/.test(id)) return id.replace(/([a-z0-9])([A-Z])/g, '$1 $2');

    return id
        .split('-')
        .map(part => (/^\d+$/.test(part) ? part : part.charAt(0).toUpperCase() + part.slice(1)))
        .join(' ');
}

function descriptionFor(id, block) {
    const english = DESCRIPTION_OVERRIDES[id] ?? block.description;
    if (!english) throw new Error(`No English description for block [${id}]. Add one to DESCRIPTION_OVERRIDES.`);
    if (!PHRASES[english]) throw new Error(`No ms/zh translation for "${english}" (block ${id}).`);

    return { en: english, ms: PHRASES[english][0], zh: PHRASES[english][1] };
}

function namespaceFor(locale) {
    const entries = {};

    for (const [id, block] of Object.entries(manifest.blocks)) {
        entries[id] = { title: humanise(id), description: descriptionFor(id, block)[locale] };
    }

    return { ...STATIC[locale], entries };
}

/** Rebuilds the object with "blocks" inserted after "charts" so the diff is one hunk. */
function insertAfter(messages, key, value) {
    const out = {};
    for (const [existing, item] of Object.entries(messages)) {
        if (existing === 'blocks') continue;
        out[existing] = item;
        if (existing === AFTER_KEY) out[key] = value;
    }
    if (!(key in out)) out[key] = value;

    return out;
}

for (const locale of LOCALES) {
    const file = path.join(root, 'resources/js/i18n/locales', `${locale}.json`);
    const messages = JSON.parse(fs.readFileSync(file, 'utf8'));
    const next = insertAfter(messages, 'blocks', namespaceFor(locale));

    fs.writeFileSync(file, `${JSON.stringify(next, null, 4)}\n`);
    console.log(`${locale}: ${Object.keys(manifest.blocks).length} block entries`);
}
