<?php
/**
 * 足迹地图插件
 *
 * 基于 Leaflet 或 Mapbox GL 显示真实世界地图，标记去过的地方。
 * 支持日/夜/自动模式、足迹连线、统计面板、年份筛选、自定义标记、
 * 时间轴、移动端优化、离线缓存、搜索地点、数据混淆编码。
 * 在文章或页面中使用短代码 [footprint] 即可嵌入地图。
 *
 * @package FootprintMap
 * @author yuege.
 * @version 2.9.0
 * @link https://beicb.top
 */

namespace TypechoPlugin\FootprintMap;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Widget\Helper\Form\Element\Radio as TypechoRadio;
use Typecho\Widget\Helper\Form\Element\Select;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Plugin implements PluginInterface
{
    /**
     * 默认足迹数据（JSON）
     */
    const DEFAULT_PLACES = '[
  {"lat": 39.90, "lng": 116.41, "name": "北京", "desc": "故宫、长城、天坛，千年古都的厚重", "year": "2019", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 31.23, "lng": 121.47, "name": "上海", "desc": "外滩夜景、城隍庙小吃，魔都的繁华", "year": "2019", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 23.13, "lng": 113.26, "name": "广州", "desc": "早茶、珠江夜游，食在广州名不虚传", "year": "2020", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 22.54, "lng": 114.06, "name": "深圳", "desc": "从渔村到都市，改革开放的窗口", "year": "2020", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 30.57, "lng": 104.07, "name": "成都", "desc": "宽窄巷子、大熊猫，来了就不想走", "year": "2021", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 29.56, "lng": 106.55, "name": "重庆", "desc": "洪崖洞、火锅、8D魔幻城市", "year": "2021", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 34.27, "lng": 108.95, "name": "西安", "desc": "兵马俑、回民街，十三朝古都", "year": "2022", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 36.06, "lng": 120.38, "name": "青岛", "desc": "栈桥、啤酒、海鲜，海滨之城", "year": "2022", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 30.27, "lng": 120.16, "name": "杭州", "desc": "西湖、灵隐寺，人间天堂", "year": "2023", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 32.04, "lng": 118.78, "name": "南京", "desc": "中山陵、夫子庙，六朝古都", "year": "2023", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 28.19, "lng": 112.98, "name": "长沙", "desc": "橘子洲头、臭豆腐，星城烟火气", "year": "2024", "color": "#fd8888", "image": "", "link": ""},
  {"lat": 27.99, "lng": 120.67, "name": "温州", "desc": "雁荡山、楠溪江，山水温州", "year": "2024", "color": "#fd8888", "image": "", "link": ""}
]';

    /**
     * 瓦片提供商配置
     */
    const TILE_PROVIDERS = array(
        'osm'   => array(
            'url'    => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'sub'    => 'abc',
            'attr'   => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        ),
        'carto' => array(
            'url'    => 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            'sub'    => 'abcd',
            'attr'   => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>'
        ),
        'amap'  => array(
            'url'    => 'https://webrd0{s}.is.autonavi.com/appmaptile?lang=zh_cn&size=1&scale=1&style=8&x={x}&y={y}&z={z}',
            'sub'    => '123',
            'attr'   => '&copy; 高德地图'
        )
    );

    /**
     * 插件激活
     */
    public static function activate()
    {
        \Typecho\Plugin::factory('Widget\Base\Contents')->contentEx = array(
            'TypechoPlugin\FootprintMap\Plugin', 'parseShortcode'
        );
        \Typecho\Plugin::factory('Widget\Base\Contents')->excerptEx = array(
            'TypechoPlugin\FootprintMap\Plugin', 'parseShortcodeExcerpt'
        );
        return _t('足迹地图插件已启用。在文章或页面中使用短代码 [footprint] 即可嵌入地图。');
    }

    /**
     * 插件禁用
     */
    public static function deactivate()
    {
        return _t('足迹地图插件已禁用。');
    }

    /**
     * 插件配置面板
     */
    public static function config(Form $form)
    {
        // === 配置导出/导入（放顶部方便备份） ===
        $form->addItem(new HtmlBlock(self::renderExportImportHtml()));

        // === 足迹数据可视化编辑器 ===
        $form->addItem(new HtmlBlock(self::renderPlaceEditorHtml()));

        // === 基础配置 ===
        $theme = new SafeRadio(
            'theme',
            array(
                'day'   => _t('日间模式'),
                'night' => _t('夜间模式'),
                'auto'  => _t('自动模式（跟随网站主题）')
            ),
            'day',
            _t('显示模式'),
            _t('选择地图的显示模式。日间模式使用亮色底图；夜间模式使用暗色底图；自动模式根据网站主题自动切换。默认为日间模式。')
        );
        $form->addInput($theme);

        $provider = new SafeRadio(
            'provider',
            array(
                'leaflet' => _t('Leaflet + OSM（免费，无需 Token，推荐）'),
                'mapbox'  => _t('Mapbox GL（效果更好，需要 Token）')
            ),
            'leaflet',
            _t('地图方案'),
            _t('选择地图服务提供商。Leaflet 免费无需注册；Mapbox 效果更好但需要注册获取 Token。')
        );
        $form->addInput($provider);

        $mapboxToken = new Text(
            'mapboxToken',
            null,
            '',
            _t('Mapbox Access Token'),
            _t('仅当选择 Mapbox 方案时需要填写。<br>前往 <a href="https://account.mapbox.com/access-tokens/" target="_blank">Mapbox 官网</a> 免费注册获取 Token。')
        );
        $form->addInput($mapboxToken);

        $tileProvider = new Select(
            'tileProvider',
            array(
                'osm'   => _t('OpenStreetMap 标准地图'),
                'carto' => _t('CartoDB Voyager 简洁地图'),
                'amap'  => _t('高德地图（国内访问快）')
            ),
            'osm',
            _t('地图瓦片（仅 Leaflet）'),
            _t('选择地图底图样式。国内用户推荐使用「高德地图」以获得更快的加载速度。')
        );
        $form->addInput($tileProvider);

        $mapHeight = new Text(
            'mapHeight',
            null,
            '560',
            _t('地图高度'),
            _t('地图显示高度，单位为像素，默认 560。')
        );
        $form->addInput($mapHeight);

        $places = new Textarea(
            'places',
            null,
            self::DEFAULT_PLACES,
            _t('足迹数据'),
            _t('JSON 格式数组，支持字段：lat（纬度）、lng（经度）、name（地名）、desc（描述，可选）、year（年份，可选，支持输入 2020 或 2020.3 / 2020-3 / 2020/3 等带月份格式，前台自动识别为「2020年」或「2020年3月」）、color（标记颜色，可选）、image（图片URL，可选）、imageCaption（图片说明，可选，显示在图片下方）、link（链接URL，可选）、linkTitle（链接标题，可选）。<br>经纬度查询：<a href="https://www.openstreetmap.org/?mlat=39.9042&mlon=116.4074#map=12/39.9042/116.4074" target="_blank">https://www.openstreetmap.org</a>（打开后在地图上点击任意位置，左侧面板显示经纬度；或使用高德拾取器 <a href="https://lbs.amap.com/tools/picker" target="_blank">https://lbs.amap.com/tools/picker</a>）<br>数据在前端通过 Base64 混淆编码输出，防止直接查看源码获取原始数据。')
        );
        $places->setAttribute('rows', 15);
        $form->addInput($places);

        // 可视化编辑按钮
        $form->addItem(new HtmlBlock('<div style="margin:-8px 0 16px;"><button type="button" onclick="FootprintMapOpenEditor()" style="height:34px;padding:0 16px;background:#6f42c1;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;line-height:34px;">可视化编辑足迹数据</button><span style="font-size:12px;color:#999;margin-left:10px;">点击打开表单编辑器，无需手写 JSON</span></div>'));

        // === 显示样式配置 ===
        $form->addItem(new HtmlBlock('<div style="margin:20px 0 10px;padding:10px 0;border-top:2px solid #e9ecef;border-bottom:1px solid #e9ecef;"><h3 style="margin:0;font-size:15px;color:#333;">显示样式配置</h3><p style="margin:4px 0 0;font-size:12px;color:#999;">配置地图外观和交互效果</p></div>'));

        $popupAlign = new Select(
            'popupAlign',
            array(
                'left'   => _t('左对齐'),
                'center' => _t('居中对齐')
            ),
            'left',
            _t('悬浮卡片对齐方式'),
            _t('选择标记悬浮卡片（详情面板）中文字内容的对齐方式。默认左对齐。')
        );
        $form->addInput($popupAlign);

        $linkTextMode = new Select(
            'linkTextMode',
            array(
                'url'   => _t('显示链接地址'),
                'title' => _t('显示链接标题')
            ),
            'title',
            _t('详情链接显示方式'),
            _t('点击标记打开详情时，链接可显示为完整地址，或显示为标题。选择「显示链接标题」时，可在足迹数据中填写 linkTitle 字段；未填写时同站链接会尝试自动读取页面标题，外部链接受浏览器跨域限制可能无法读取并显示为“查看相关链接”。')
        );
        $form->addInput($linkTextMode);

        $routeColor = new Text(
            'routeColor',
            null,
            '#fd8888',
            _t('足迹连线颜色'),
            _t('足迹连线的颜色，默认 #fd8888（红色）。需开启「足迹连线」功能才生效。')
        );
        $form->addInput($routeColor);

        $defaultMarkerColor = new Text(
            'defaultMarkerColor',
            null,
            '#fd8888',
            _t('默认标记颜色'),
            _t('所有足迹标记的统一默认颜色，只能填一个颜色值（如 #fd8888）。
开启下方「自定义标记颜色」功能后，可在足迹数据 JSON 中为每个地点单独指定 color 字段，未填 color 的地点仍使用此默认颜色。')
        );
        $form->addInput($defaultMarkerColor);

        // === 高级功能开关 ===
        $form->addItem(new HtmlBlock('<div style="margin:20px 0 10px;padding:10px 0;border-top:2px solid #e9ecef;border-bottom:1px solid #e9ecef;"><h3 style="margin:0;font-size:15px;color:#333;">高级功能开关</h3><p style="margin:4px 0 0;font-size:12px;color:#999;">勾选启用对应功能，按需开启</p></div>'));

        $enableRoute = new SafeRadio(
            'enableRoute',
            array('1' => _t('启用'), '0' => _t('关闭')),
            '0',
            _t('足迹连线'),
            _t('按数据顺序用虚线连接所有足迹点，展示旅行路线。连线颜色可在上方「足迹连线颜色」中配置。')
        );
        $form->addInput($enableRoute);

        $enableStats = new SafeRadio(
            'enableStats',
            array('1' => _t('启用'), '0' => _t('关闭')),
            '0',
            _t('统计面板'),
            _t('在地图上方显示足迹总数、城市数量、年份范围等统计信息。')
        );
        $form->addInput($enableStats);

        $enableYearFilter = new SafeRadio(
            'enableYearFilter',
            array('1' => _t('启用'), '0' => _t('关闭')),
            '0',
            _t('年份筛选'),
            _t('在地图上方显示年份下拉框，筛选特定年份的足迹。需在足迹数据中填写 year 字段。')
        );
        $form->addInput($enableYearFilter);

        $enableCustomIcon = new SafeRadio(
            'enableCustomIcon',
            array('1' => _t('启用'), '0' => _t('关闭')),
            '0',
            _t('自定义标记颜色'),
            _t('开启后，可在足迹数据 JSON 中为每个地点单独指定颜色。
JSON 字段路径：每个地点对象的 color 字段。
示例：
[
  {"lat":39.90,"lng":116.41,"name":"北京","color":"#ff0000"},
  {"lat":31.23,"lng":121.47,"name":"上海","color":"#0066ff"},
  {"lat":30.67,"lng":104.07,"name":"成都"}
]
北京=红色，上海=蓝色，成都未填 color=使用上方「默认标记颜色」。
颜色格式：#RRGGBB（如 #ff0000）或颜色名（如 red）。')
        );
        $form->addInput($enableCustomIcon);

        $enableTimeline = new SafeRadio(
            'enableTimeline',
            array('1' => _t('启用'), '0' => _t('关闭')),
            '0',
            _t('足迹时间轴'),
            _t('在地图侧边显示足迹列表，点击列表项可定位到对应标记并弹出详情。')
        );
        $form->addInput($enableTimeline);

        $enableMobileOpt = new SafeRadio(
            'enableMobileOpt',
            array('1' => _t('启用'), '0' => _t('关闭')),
            '1',
            _t('移动端优化'),
            _t('移动设备上禁用滚轮缩放（防止页面滚动冲突），启用触摸手势，自适应高度。默认启用。')
        );
        $form->addInput($enableMobileOpt);

        $enableCache = new SafeRadio(
            'enableCache',
            array('1' => _t('启用'), '0' => _t('关闭')),
            '0',
            _t('离线缓存'),
            _t('使用浏览器 localStorage 缓存足迹数据，提升二次加载速度。数据变更后自动更新缓存；关闭此功能时自动清除历史缓存。缓存存储在访客浏览器中，不影响服务器。')
        );
        $form->addInput($enableCache);

        $enableSearch = new SafeRadio(
            'enableSearch',
            array('1' => _t('启用'), '0' => _t('关闭')),
            '0',
            _t('搜索地点'),
            _t('在地图上方显示搜索框，输入关键词实时筛选并定位足迹标记。')
        );
        $form->addInput($enableSearch);

        // === 使用说明（折叠式，放底部） ===
        $form->addItem(new HtmlBlock(self::renderUsageHtml()));
    }

    /**
     * 个人配置
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 获取插件配置
     */
    public static function getConfig($key = null, $default = null)
    {
        try {
            $options = \Typecho\Widget::widget('Widget\Options');
            $pluginConfig = $options->plugin('FootprintMap');
        } catch (\Exception $e) {
            return $default;
        }

        if ($key === null) {
            return $pluginConfig;
        }

        $value = isset($pluginConfig->{$key}) ? $pluginConfig->{$key} : $default;

        // 兼容旧版 Checkbox 数组值
        if (is_array($value)) {
            $value = in_array('1', $value) ? '1' : '0';
        }

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * 检查功能开关是否启用
     */
    private static function isEnabled($key)
    {
        return self::getConfig($key, '0') === '1';
    }

    /**
     * 摘要中移除短代码
     */
    public static function parseShortcodeExcerpt($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        $content = preg_replace('/\[footprint\]/i', '[足迹地图]', $content);
        return $content;
    }

    /**
     * 解析短代码（正文）
     */
    public static function parseShortcode($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;

        if (!preg_match('/\[footprint\]/i', $content)) {
            return $content;
        }

        $html = self::renderMap();
        $content = preg_replace('/\[footprint\]/i', $html, $content);

        return $content;
    }

    /**
     * 渲染使用说明 HTML（折叠式，放底部）
     */
    private static function renderUsageHtml()
    {
        return <<<HTML
<details style="margin:20px 0;border:1px solid #e9ecef;border-radius:8px;overflow:hidden;">
    <summary style="padding:12px 20px;background:#f8f9fa;cursor:pointer;font-size:15px;font-weight:bold;color:#333;user-select:none;">使用说明和地图功能介绍（点击展开）</summary>
    <div style="padding:16px 20px;">
        <h4 style="margin:0 0 8px;font-size:14px;color:#555;">使用方法</h4>
        <ol style="margin:0 0 14px;padding-left:20px;font-size:13px;color:#666;line-height:1.8;">
            <li>在上方配置显示模式、地图方案和足迹数据</li>
            <li>在 Typecho 后台创建一个独立页面（撰写 -&gt; 新建页面）</li>
            <li>在页面内容中输入短代码 <code style="background:#e9ecef;padding:2px 6px;border-radius:3px;color:#c7254e;">[footprint]</code></li>
            <li>发布页面后，短代码会自动替换为交互式足迹地图</li>
        </ol>

        <h4 style="margin:0 0 8px;font-size:14px;color:#555;">足迹数据格式</h4>
        <pre style="background:#f1f3f5;padding:10px;border-radius:4px;font-size:12px;overflow-x:auto;margin:0 0 14px;">[
  {"lat": 39.90, "lng": 116.41, "name": "北京", "desc": "故宫长城", "year": "2023", "color": "#ff0000", "image": "https://example.com/bj.jpg", "imageCaption": "故宫角楼", "link": "https://example.com/bj-travel", "linkTitle": "北京旅行记录"},
  {"lat": 31.23, "lng": 121.47, "name": "上海", "desc": "外滩夜景", "year": "2023", "color": "#0066ff"},
  {"lat": 30.67, "lng": 104.07, "name": "成都", "desc": "美食之都", "year": "2024"}
]</pre>
        <p style="font-size:12px;color:#999;margin:0 0 6px;">上例：北京=红色，上海=蓝色，成都未填 color=使用「默认标记颜色」</p>
        <p style="font-size:12px;color:#999;margin:0 0 14px;">字段说明：<br>
- <strong>lat</strong> 纬度、<strong>lng</strong> 经度（必填）<br>
- <strong>name</strong> 地名（必填）<br>
- <strong>desc</strong> 描述（可选）<br>
- <strong>year</strong> 年份（可选，用于年份筛选。支持输入 2020 或 2020.3 / 2020-3 / 2020/3 等带月份格式，前台自动识别为「2020年」或「2020年3月」）<br>
- <strong>color</strong> 标记颜色（可选，需开启「自定义标记颜色」功能。格式 #RRGGBB 如 #ff0000，每个地点可设不同颜色，未填则使用「默认标记颜色」）<br>
- <strong>image</strong> 图片URL（可选，显示在详情卡片中）<br>
- <strong>imageCaption</strong> 图片说明（可选，显示在图片下方）<br>
- <strong>link</strong> 链接URL（可选，点击标记后显示，可填写站内文章链接或外部链接）<br>
- <strong>linkTitle</strong> 链接标题（可选，后台「详情链接显示方式」选择显示标题时使用）</p>

        <h4 style="margin:0 0 8px;font-size:14px;color:#555;">地图功能</h4>
        <ul style="margin:0 0 14px;padding-left:20px;font-size:13px;color:#666;line-height:1.8;">
            <li><strong>真实世界地图</strong>：含地形、城市名、道路（Leaflet 使用 OSM/高德瓦片，Mapbox 使用 GL 矢量地图）</li>
            <li><strong>中文语言</strong>：Mapbox 方案自动将地名标签切换为中文（内联实现 mapbox-gl-language 功能）</li>
            <li><strong>足迹标记</strong>：圆点标记去过的地方，悬停放大变色</li>
            <li><strong>悬浮查看详情</strong>：鼠标悬停标记即显示详情卡片（地名、描述、年份、图片、链接），无需点击</li>
            <li><strong>图片和链接</strong>：在足迹数据中设置 image 和 link 字段，详情卡片中显示图片和跳转链接</li>
            <li><strong>数据混淆</strong>：足迹数据通过 Base64 编码输出，防止直接查看源码获取</li>
            <li><strong>日/夜/自动模式</strong>：支持日间、夜间、自动三种显示模式</li>
            <li><strong>足迹连线</strong>：按数据顺序用虚线连接足迹点，展示旅行路线（可配置连线颜色）</li>
            <li><strong>统计面板</strong>：显示足迹总数、城市数量、年份范围</li>
            <li><strong>年份筛选</strong>：下拉选择年份，筛选特定年份足迹</li>
            <li><strong>自定义标记颜色</strong>：为每个地点指定不同颜色（可配置默认颜色）</li>
            <li><strong>时间轴</strong>：侧边列表，点击定位到地图标记</li>
            <li><strong>移动端优化</strong>：触摸手势、响应式布局</li>
            <li><strong>离线缓存</strong>：localStorage 缓存数据，提升二次加载</li>
            <li><strong>搜索地点</strong>：输入关键词实时筛选定位</li>
        </ul>

        <h4 style="margin:0 0 8px;font-size:14px;color:#555;">两种方案对比</h4>
        <table style="width:100%;border-collapse:collapse;font-size:13px;color:#666;">
            <tr style="background:#e9ecef;">
                <th style="padding:6px 10px;border:1px solid #dee2e6;text-align:left;">特性</th>
                <th style="padding:6px 10px;border:1px solid #dee2e6;text-align:left;">Leaflet + OSM</th>
                <th style="padding:6px 10px;border:1px solid #dee2e6;text-align:left;">Mapbox GL</th>
            </tr>
            <tr><td style="padding:6px 10px;border:1px solid #dee2e6;">费用</td><td style="padding:6px 10px;border:1px solid #dee2e6;">完全免费</td><td style="padding:6px 10px;border:1px solid #dee2e6;">免费额度（需注册）</td></tr>
            <tr><td style="padding:6px 10px;border:1px solid #dee2e6;">地图类型</td><td style="padding:6px 10px;border:1px solid #dee2e6;">栅格瓦片</td><td style="padding:6px 10px;border:1px solid #dee2e6;">矢量地图（更流畅）</td></tr>
            <tr><td style="padding:6px 10px;border:1px solid #dee2e6;">中文地名</td><td style="padding:6px 10px;border:1px solid #dee2e6;">取决于瓦片源</td><td style="padding:6px 10px;border:1px solid #dee2e6;">自动切换中文</td></tr>
            <tr><td style="padding:6px 10px;border:1px solid #dee2e6;">数据混淆</td><td style="padding:6px 10px;border:1px solid #dee2e6;">Base64 编码</td><td style="padding:6px 10px;border:1px solid #dee2e6;">Base64 编码</td></tr>
            <tr><td style="padding:6px 10px;border:1px solid #dee2e6;">夜间模式</td><td style="padding:6px 10px;border:1px solid #dee2e6;">CSS 滤镜反转</td><td style="padding:6px 10px;border:1px solid #dee2e6;">原生暗色样式</td></tr>
        </table>
    </div>
</details>
HTML;
    }

    /**
     * 渲染足迹数据可视化编辑器
     */
    private static function renderPlaceEditorHtml()
    {
        return <<<HTML
<div id="footprint-editor-toast" onclick="this.style.display='none'" style="display:none;position:fixed;left:50%;top:24px;transform:translateX(-50%);z-index:100001;background:rgba(40,167,69,0.96);color:#fff;padding:10px 18px;border-radius:6px;font-size:13px;box-shadow:0 4px 16px rgba(0,0,0,0.18);cursor:pointer;"></div>
<div id="footprint-editor-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;overflow:auto;">
    <div style="background:#fff;width:95%;max-width:1100px;margin:30px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.3);max-height:86vh;display:flex;flex-direction:column;">
        <div style="padding:16px 20px;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:16px;color:#333;">足迹数据可视化编辑器</h3>
            <button type="button" onclick="FootprintMapCloseEditor()" style="background:none;border:none;font-size:22px;color:#999;cursor:pointer;line-height:1;padding:0;">&times;</button>
        </div>
        <div style="padding:16px 20px;overflow:auto;flex:1;min-height:0;display:flex;flex-direction:column;">
            <div style="margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="button" onclick="FootprintMapAddRow()" style="height:34px;padding:0 16px;background:#28a745;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;line-height:34px;">+ 添加地点</button>
                <span style="font-size:12px;color:#999;">填写经纬度等信息后点击保存，自动生成 JSON</span>
                <span style="font-size:12px;color:#999;margin-left:auto;">经纬度查询：<a href="https://www.openstreetmap.org/" target="_blank">OSM</a> · <a href="https://lbs.amap.com/tools/picker" target="_blank">高德</a></span>
            </div>
            <div style="overflow:auto;flex:1;min-height:0;border:1px solid #e9ecef;border-radius:4px;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:1000px;">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th style="padding:8px 6px;text-align:center;border-bottom:1px solid #e9ecef;width:40px;">序号</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;width:100px;">地名 *</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;width:90px;">纬度 lat *</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;width:90px;">经度 lng *</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;">描述 desc</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;width:95px;">年份 year</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;width:80px;">颜色 color</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;">图片 image</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;">图片说明 imageCaption</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;">链接 link</th>
                            <th style="padding:8px 6px;text-align:left;border-bottom:1px solid #e9ecef;width:100px;">链接标题</th>
                            <th style="padding:8px 6px;text-align:center;border-bottom:1px solid #e9ecef;width:60px;">操作</th>
                        </tr>
                    </thead>
                    <tbody id="footprint-editor-tbody"></tbody>
                </table>
            </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e9ecef;display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" onclick="FootprintMapCloseEditor()" style="height:36px;padding:0 20px;background:#6c757d;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;line-height:36px;">取消</button>
            <button type="button" onclick="FootprintMapSaveEditor()" style="height:36px;padding:0 20px;background:#007bff;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;line-height:36px;">保存到文本框</button>
        </div>
    </div>
</div>
<script>
function FootprintMapOpenEditor() {
    var textarea = document.getElementById('places') || document.querySelector('[name="places"]');
    if (!textarea) { alert('未找到足迹数据文本框'); return; }
    var data = [];
    try {
        data = JSON.parse(textarea.value.trim());
        if (!Array.isArray(data)) data = [];
    } catch(e) {
        if (!confirm('当前文本框内容不是有效的 JSON，是否清空并开始可视化编辑？')) return;
        data = [];
    }
    var tbody = document.getElementById('footprint-editor-tbody');
    tbody.innerHTML = '';
    if (data.length === 0) {
        FootprintMapAddRow();
    } else {
        data.forEach(function(item) { FootprintMapAddRow(item); });
    }
    document.getElementById('footprint-editor-overlay').style.display = 'block';
}

function FootprintMapCloseEditor() {
    document.getElementById('footprint-editor-overlay').style.display = 'none';
}

function FootprintMapShowToast(message) {
    var toast = document.getElementById('footprint-editor-toast');
    if (!toast) return;
    toast.textContent = message;
    toast.style.display = 'block';
    clearTimeout(window.FootprintMapToastTimer);
    window.FootprintMapToastTimer = setTimeout(function() {
        toast.style.display = 'none';
    }, 3500);
}

function FootprintMapAddRow(item) {
    item = item || {};
    var tbody = document.getElementById('footprint-editor-tbody');
    var tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid #f1f3f5';
    var cells = [
        {type: 'index'},
        {name: 'name', value: item.name || '', placeholder: '北京', width: '100px'},
        {name: 'lat', value: item.lat !== undefined ? item.lat : '', placeholder: '39.90', width: '90px'},
        {name: 'lng', value: item.lng !== undefined ? item.lng : '', placeholder: '116.40', width: '90px'},
        {name: 'desc', value: item.desc || '', placeholder: '描述（可选）'},
        {name: 'year', value: item.year || '', placeholder: '2024 或 2024.3', width: '95px'},
        {name: 'color', value: item.color || '', placeholder: '#ff0000', width: '80px'},
        {name: 'image', value: item.image || '', placeholder: 'https://...（可选）'},
        {name: 'imageCaption', value: item.imageCaption || '', placeholder: '图片说明（可选）'},
        {name: 'link', value: item.link || '', placeholder: 'https://...（可选）'},
        {name: 'linkTitle', value: item.linkTitle || '', placeholder: '文章标题', width: '100px'},
        {type: 'delete'}
    ];
    cells.forEach(function(cell) {
        var td = document.createElement('td');
        td.style.padding = '6px';
        if (cell.type === 'index') {
            td.style.textAlign = 'center';
            td.style.color = '#999';
            td.textContent = tbody.children.length + 1;
        } else if (cell.type === 'delete') {
            td.style.textAlign = 'center';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = '删除';
            btn.style.cssText = 'height:28px;padding:0 10px;background:#dc3545;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:12px;line-height:28px;';
            btn.onclick = function() {
                tr.parentNode.removeChild(tr);
                FootprintMapRefreshIndex();
            };
            td.appendChild(btn);
        } else {
            var input = document.createElement('input');
            input.type = 'text';
            input.name = 'fm_' + cell.name;
            input.value = cell.value;
            input.placeholder = cell.placeholder;
            input.style.cssText = 'width:' + (cell.width || '100%') + ';height:30px;padding:0 6px;border:1px solid #ddd;border-radius:3px;font-size:13px;box-sizing:border-box;';
            td.appendChild(input);
        }
        tr.appendChild(td);
    });
    tbody.appendChild(tr);
}

function FootprintMapRefreshIndex() {
    var rows = document.getElementById('footprint-editor-tbody').children;
    for (var i = 0; i < rows.length; i++) {
        rows[i].children[0].textContent = i + 1;
    }
}

function FootprintMapSaveEditor() {
    var rows = document.getElementById('footprint-editor-tbody').children;
    var data = [];
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var nameInput = row.querySelector('[name="fm_name"]');
        var latInput = row.querySelector('[name="fm_lat"]');
        var lngInput = row.querySelector('[name="fm_lng"]');
        if (!nameInput.value.trim() && !latInput.value.trim() && !lngInput.value.trim()) continue;
        if (!nameInput.value.trim()) { alert('第 ' + (i+1) + ' 行：请填写地名'); nameInput.focus(); return; }
        if (!latInput.value.trim()) { alert('第 ' + (i+1) + ' 行：请填写纬度'); latInput.focus(); return; }
        if (!lngInput.value.trim()) { alert('第 ' + (i+1) + ' 行：请填写经度'); lngInput.focus(); return; }
        var lat = parseFloat(latInput.value);
        var lng = parseFloat(lngInput.value);
        if (isNaN(lat) || lat < -90 || lat > 90) { alert('第 ' + (i+1) + ' 行：纬度必须在 -90 到 90 之间'); latInput.focus(); return; }
        if (isNaN(lng) || lng < -180 || lng > 180) { alert('第 ' + (i+1) + ' 行：经度必须在 -180 到 180 之间'); lngInput.focus(); return; }
        var item = {name: nameInput.value.trim(), lat: lat, lng: lng};
        var desc = row.querySelector('[name="fm_desc"]').value.trim();
        if (desc) item.desc = desc;
        var year = row.querySelector('[name="fm_year"]').value.trim();
        if (year) item.year = year;
        var color = row.querySelector('[name="fm_color"]').value.trim();
        if (color) item.color = color;
        var image = row.querySelector('[name="fm_image"]').value.trim();
        if (image) item.image = image;
        var imageCaption = row.querySelector('[name="fm_imageCaption"]').value.trim();
        if (imageCaption) item.imageCaption = imageCaption;
        var link = row.querySelector('[name="fm_link"]').value.trim();
        if (link) item.link = link;
        var linkTitle = row.querySelector('[name="fm_linkTitle"]').value.trim();
        if (linkTitle) item.linkTitle = linkTitle;
        data.push(item);
    }
    if (data.length === 0) { alert('请至少添加一个有效地点'); return; }
    var textarea = document.getElementById('places') || document.querySelector('[name="places"]');
    textarea.value = JSON.stringify(data, null, 2);
    FootprintMapCloseEditor();
    FootprintMapShowToast('已保存到文本框，共 ' + data.length + ' 个地点。请点击页面底部「保存设置」按钮以生效。');
}
</script>
HTML;
    }

    /**
     * 渲染导出/导入 HTML
     */
    private static function renderExportImportHtml()
    {
        return <<<HTML
<div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:16px 20px;margin:20px 0;">
    <h3 style="margin:0 0 12px;font-size:16px;color:#333;">配置导出 / 导入</h3>
    <p style="font-size:13px;color:#666;margin:0 0 12px;">导出当前插件配置到 JSON 文件，或从 JSON 文件导入配置。方便备份和迁移。</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <button type="button" onclick="FootprintMapExportConfig()" style="height:34px;padding:0 16px;background:#28a745;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;line-height:34px;box-sizing:border-box;vertical-align:middle;">导出配置</button>
        <button type="button" onclick="document.getElementById('footprint-import-file').click()" style="height:34px;padding:0 16px;background:#007bff;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;line-height:34px;box-sizing:border-box;vertical-align:middle;">导入配置</button>
        <input type="file" id="footprint-import-file" accept=".json" onchange="FootprintMapImportConfig(this)" style="display:none;">
        <span id="footprint-import-msg" style="font-size:13px;color:#666;line-height:34px;vertical-align:middle;"></span>
    </div>
</div>
<script>
function FootprintMapGetConfigFields() {
    return {
        text: ['mapboxToken', 'tileProvider', 'mapHeight', 'places', 'routeColor', 'defaultMarkerColor'],
        select: ['popupAlign', 'linkTextMode'],
        radio: ['theme', 'provider', 'enableRoute', 'enableStats', 'enableYearFilter', 'enableCustomIcon', 'enableTimeline', 'enableMobileOpt', 'enableCache', 'enableSearch']
    };
}

function FootprintMapExportConfig() {
    var config = {};
    var fields = FootprintMapGetConfigFields();
    fields.text.forEach(function(name) {
        var el = document.getElementById(name) || document.querySelector('[name="' + name + '"]');
        if (el) config[name] = el.value;
    });
    fields.select.forEach(function(name) {
        var el = document.getElementById(name) || document.querySelector('[name="' + name + '"]');
        if (el) config[name] = el.value;
    });
    fields.radio.forEach(function(name) {
        var checked = document.querySelector('input[name="' + name + '"]:checked');
        if (checked) config[name] = checked.value;
    });
    var json = JSON.stringify(config, null, 2);
    var blob = new Blob([json], {type: 'application/json'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'footprintmap-config-' + new Date().toISOString().slice(0,10) + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function FootprintMapImportConfig(input) {
    var file = input.files[0];
    if (!file) return;
    var reader = new FileReader();
    var msg = document.getElementById('footprint-import-msg');
    reader.onload = function(e) {
        try {
            var config = JSON.parse(e.target.result);
            var fields = FootprintMapGetConfigFields();
            var count = 0;
            fields.text.forEach(function(name) {
                if (config[name] === undefined) return;
                var el = document.getElementById(name) || document.querySelector('[name="' + name + '"]');
                if (el) { el.value = config[name]; count++; }
            });
            fields.select.forEach(function(name) {
                if (config[name] === undefined) return;
                var el = document.getElementById(name) || document.querySelector('[name="' + name + '"]');
                if (el) { el.value = config[name]; count++; }
            });
            fields.radio.forEach(function(name) {
                if (config[name] === undefined) return;
                var radio = document.querySelector('input[name="' + name + '"][value="' + config[name] + '"]');
                if (radio) { radio.checked = true; count++; }
            });
            msg.style.color = '#28a745';
            msg.textContent = '已导入 ' + count + ' 项配置，请点击下方保存设置生效。';
        } catch(err) {
            msg.style.color = '#dc3545';
            msg.textContent = '导入失败：' + err.message;
        }
    };
    reader.readAsText(file);
}
</script>
HTML;
    }

    /**
     * 渲染地图 HTML
     */
    public static function renderMap()
    {
        $provider     = self::getConfig('provider', 'leaflet');
        $theme        = self::getConfig('theme', 'day');
        $mapHeight    = self::getConfig('mapHeight', '560');
        $placesJson   = self::getConfig('places', self::DEFAULT_PLACES);
        $mapboxToken  = self::getConfig('mapboxToken', '');
        $tileProvider = self::getConfig('tileProvider', 'osm');

        // 显示样式配置
        $popupAlign         = self::getConfig('popupAlign', 'left');
        $linkTextMode       = self::getConfig('linkTextMode', 'title');
        $routeColor         = self::getConfig('routeColor', '#fd8888');
        $defaultMarkerColor = self::getConfig('defaultMarkerColor', '#fd8888');

        // 功能开关
        $enableRoute      = self::isEnabled('enableRoute');
        $enableStats      = self::isEnabled('enableStats');
        $enableYearFilter = self::isEnabled('enableYearFilter');
        $enableCustomIcon = self::isEnabled('enableCustomIcon');
        $enableTimeline   = self::isEnabled('enableTimeline');
        $enableMobileOpt  = self::isEnabled('enableMobileOpt');
        $enableCache      = self::isEnabled('enableCache');
        $enableSearch     = self::isEnabled('enableSearch');

        // 解析足迹数据
        $places = json_decode($placesJson, true);
        if (!is_array($places)) {
            $places = json_decode(self::DEFAULT_PLACES, true);
        }
        $placesCount = count($places);

        // Base64 混淆编码
        $placesEncoded = base64_encode(json_encode($places, JSON_UNESCAPED_UNICODE));

        // 功能 JSON（传递给 JS）
        $features = array(
            'route'             => $enableRoute,
            'stats'             => $enableStats,
            'yearFilter'        => $enableYearFilter,
            'customIcon'        => $enableCustomIcon,
            'timeline'          => $enableTimeline,
            'mobileOpt'         => $enableMobileOpt,
            'cache'             => $enableCache,
            'search'            => $enableSearch,
            'popupAlign'        => $popupAlign,
            'linkTextMode'      => $linkTextMode,
            'routeColor'        => $routeColor,
            'defaultMarkerColor'=> $defaultMarkerColor
        );
        $featuresJS = json_encode($features);

        // 统计面板 HTML
        $statsHtml = '';
        if ($enableStats) {
            $statsHtml = '已点亮 <strong>' . $placesCount . '</strong> 个足迹';
            $cityNames = array();
            $years = array();
            foreach ($places as $p) {
                if (isset($p['name'])) $cityNames[$p['name']] = true;
                if (isset($p['year']) && $p['year']) $years[] = $p['year'];
            }
            $cityCount = count($cityNames);
            $statsHtml .= ' | <strong>' . $cityCount . '</strong> 个城市';
            if (!empty($years)) {
                // 提取纯年份部分用于范围计算（兼容 "2020.3" / "2020-3" / "2020/3" 等带月份格式）
                $yearsForRange = array();
                foreach ($years as $y) {
                    if (preg_match('/^\s*(\d{4})/', (string)$y, $m)) {
                        $yearsForRange[] = intval($m[1]);
                    }
                }
                if (!empty($yearsForRange)) {
                    $yearsForRange = array_unique($yearsForRange);
                    sort($yearsForRange);
                    $statsHtml .= ' | <strong>' . $yearsForRange[0] . '-' . end($yearsForRange) . '</strong>';
                }
            }
        }

        // 唯一 ID
        $mapId = 'footprint-map-' . substr(md5(uniqid('', true)), 0, 8);

        // CSS
        $css = self::renderCSS($mapId, $mapHeight, $popupAlign);

        // JS
        if ($provider === 'mapbox' && !empty($mapboxToken)) {
            $js = self::renderMapboxJS($mapId, $placesEncoded, $mapboxToken, $theme, $featuresJS);
        } else {
            $js = self::renderLeafletJS($mapId, $placesEncoded, $tileProvider, $featuresJS);
        }

        // 构建 HTML
        $html = "\n<!-- FootprintMap -->\n";
        $html .= '<div class="footprint-map-wrap footprint-mode-' . $theme . '">' . "\n";

        // 工具栏（搜索 + 年份筛选）
        if ($enableSearch || $enableYearFilter) {
            $html .= '  <div class="footprint-toolbar" id="toolbar-' . $mapId . '"></div>' . "\n";
        }

        // 统计
        if ($enableStats) {
            $html .= '  <div class="footprint-map-stats">' . $statsHtml . '</div>' . "\n";
        }

        // 地图主体 + 时间轴
        $html .= '  <div class="footprint-map-body">' . "\n";
        $html .= '    <div id="' . $mapId . '" class="footprint-map-container"></div>' . "\n";
        if ($enableTimeline) {
            $html .= '    <div class="footprint-timeline" id="timeline-' . $mapId . '"></div>' . "\n";
        }
        $html .= '  </div>' . "\n";

        $html .= '</div>' . "\n";
        $html .= $css . "\n";
        $html .= $js . "\n";
        $html .= "<!-- /FootprintMap -->\n";

        return $html;
    }

    /**
     * 渲染 CSS
     */
    private static function renderCSS($mapId, $height, $popupAlign)
    {
        $h = intval($height);
        if ($h < 200) $h = 560;

        $alignClass = $popupAlign === 'center' ? 'footprint-popup-center' : 'footprint-popup-left';

        return <<<HTML
<style>
#{$mapId}.footprint-map-container {
    width: 100%;
    height: {$h}px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e8e8e8;
    z-index: 1;
    flex: 1;
    min-width: 0;
}
.footprint-map-wrap {
    margin: 20px 0;
}
.footprint-map-stats {
    text-align: center;
    margin-bottom: 10px;
    font-size: 15px;
    color: #666;
}
.footprint-map-stats strong {
    color: #fd8888;
    font-size: 18px;
}
.footprint-map-tip {
    text-align: center;
    color: #999;
    font-size: 13px;
    margin-top: 8px;
}
.footprint-map-body {
    display: flex;
    gap: 10px;
}
/* 工具栏 */
.footprint-toolbar {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.footprint-toolbar input,
.footprint-toolbar select {
    padding: 6px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    outline: none;
}
.footprint-toolbar input {
    flex: 1;
    min-width: 200px;
}
.footprint-toolbar input:focus,
.footprint-toolbar select:focus {
    border-color: #fd8888;
}
/* 时间轴 */
.footprint-timeline {
    width: 220px;
    max-height: {$h}px;
    overflow-y: auto;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    padding: 8px;
    flex-shrink: 0;
}
.footprint-timeline-title {
    font-size: 13px;
    font-weight: bold;
    color: #333;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid #eee;
}
.footprint-timeline-item {
    padding: 8px 10px;
    cursor: pointer;
    border-radius: 4px;
    transition: background 0.2s;
    font-size: 13px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
}
.footprint-timeline-item:hover {
    background: #f0f0f0;
}
.footprint-timeline-year {
    color: #fd8888;
    font-weight: bold;
    font-size: 12px;
    flex-shrink: 0;
}
.footprint-timeline-name {
    color: #555;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* === 悬浮详情卡片样式 === */
.footprint-popup-name {
    font-size: 15px;
    font-weight: bold;
    color: #333;
    margin-bottom: 4px;
}
.footprint-popup-desc {
    font-size: 13px;
    color: #666;
    line-height: 1.6;
}
.footprint-popup-year {
    font-size: 12px;
    color: #fd8888;
    margin-top: 4px;
}
.footprint-popup-image {
    width: 100%;
    max-width: 240px;
    border-radius: 4px;
    margin-top: 8px;
    display: block;
}
.footprint-popup-image-caption {
    font-size: 12px;
    color: #999;
    margin-top: 4px;
    line-height: 1.5;
    max-width: 240px;
}
.footprint-popup-link {
    display: block;
    margin-top: 8px;
    color: #fd8888 !important;
    text-decoration: none;
    font-size: 13px;
    line-height: 1.5;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 240px;
}
.footprint-popup-link:hover {
    text-decoration: underline;
}

/* 对齐方式 */
.footprint-popup-left .footprint-popup-name,
.footprint-popup-left .footprint-popup-desc,
.footprint-popup-left .footprint-popup-year,
.footprint-popup-left .footprint-popup-image-caption {
    text-align: left;
}
.footprint-popup-left .footprint-popup-image {
    margin-left: 0;
    margin-right: auto;
}
.footprint-popup-center .footprint-popup-name,
.footprint-popup-center .footprint-popup-desc,
.footprint-popup-center .footprint-popup-year,
.footprint-popup-center .footprint-popup-image-caption {
    text-align: center;
}
.footprint-popup-center .footprint-popup-image {
    margin-left: auto;
    margin-right: auto;
}
.footprint-popup-center .footprint-popup-link {
    text-align: center;
}

/* 隐藏 Leaflet 弹窗关闭按钮 */
.leaflet-popup-close-button {
    display: none !important;
}
/* Leaflet 弹窗整体 */
.leaflet-popup-content-wrapper {
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.leaflet-popup-content {
    margin: 12px 16px;
    line-height: 1.5;
}
.leaflet-popup.footprint-popup-below {
    margin-bottom: 0;
    margin-top: 20px;
}
.leaflet-popup.footprint-popup-below .leaflet-popup-tip-container {
    position: absolute;
    top: -20px;
    left: 50%;
    margin-left: -20px;
}
.leaflet-popup.footprint-popup-below .leaflet-popup-tip {
    margin-top: 13px;
}

/* Leaflet tooltip（悬停详情卡片） */
.leaflet-tooltip.footprint-tooltip-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    color: #333;
    font-size: 13px;
    padding: 0;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    max-width: 300px;
    min-width: 180px;
}
.leaflet-tooltip.footprint-tooltip-card::before {
    border-top-color: #fff;
}
.footprint-mode-night .leaflet-tooltip.footprint-tooltip-card,
.footprint-mode-night .leaflet-tooltip.footprint-tooltip-card::before {
    background: #2a2a2a;
    color: #ddd;
    border-color: #444;
}
.footprint-mode-night .leaflet-tooltip.footprint-tooltip-card::before {
    border-top-color: #2a2a2a;
}
.leaflet-tooltip.footprint-tooltip-card .footprint-popup-name,
.leaflet-tooltip.footprint-tooltip-card .footprint-popup-desc,
.leaflet-tooltip.footprint-tooltip-card .footprint-popup-year,
.leaflet-tooltip.footprint-tooltip-card .footprint-popup-link {
    padding: 0 12px;
}
.leaflet-tooltip.footprint-tooltip-card .footprint-popup-name {
    padding-top: 10px;
}
.leaflet-tooltip.footprint-tooltip-card .footprint-popup-link {
    padding-bottom: 10px;
}
.leaflet-tooltip.footprint-tooltip-card .footprint-popup-image {
    margin-top: 8px;
    border-radius: 0;
    max-width: 100%;
}
/* 比例尺：透明背景、文案居中 */
.leaflet-control-scale-line {
    background: transparent !important;
    text-align: center;
    color: #555;
    border-color: rgba(0,0,0,0.3);
    border-bottom-width: 2px;
    font-size: 11px;
    padding: 0 6px;
}

/* === 夜间模式（改进美观度） === */
.footprint-mode-night .footprint-map-container {
    border-color: #3a3a3a;
}
.footprint-mode-night .footprint-map-stats {
    color: #aaa;
}
.footprint-mode-night .footprint-map-tip {
    color: #777;
}
.footprint-mode-night .footprint-popup-name {
    color: #ddd;
}
.footprint-mode-night .footprint-popup-desc {
    color: #aaa;
}
.footprint-mode-night .footprint-popup-image-caption {
    color: #888;
}
.footprint-mode-night .leaflet-container {
    background: #152030;
}
.footprint-mode-night .leaflet-tile-pane {
    filter: invert(1) hue-rotate(180deg) brightness(0.96) contrast(0.98) saturate(0.92);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transform: translateZ(0);
}
.leaflet-tile,
.mapboxgl-canvas {
    image-rendering: auto;
}
.footprint-mode-night .leaflet-control-attribution {
    background: rgba(30,30,30,0.7) !important;
    color: #999 !important;
    font-size: 10px;
}
.footprint-mode-night .leaflet-control-attribution a {
    color: #6ab0ff !important;
}
.footprint-mode-night .leaflet-bar a {
    background: #333 !important;
    color: #ddd !important;
    border-color: #555 !important;
}
.footprint-mode-night .leaflet-bar a:hover {
    background: #444 !important;
}
.footprint-mode-night .leaflet-popup-content-wrapper,
.footprint-mode-night .leaflet-popup-tip {
    background: #2a2a2a;
    color: #ddd;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
}
.footprint-mode-night .footprint-timeline {
    border-color: #3a3a3a;
    background: #2a2a2a;
}
.footprint-mode-night .footprint-timeline-title {
    color: #ddd;
    border-bottom-color: #444;
}
.footprint-mode-night .footprint-timeline-item:hover {
    background: #3a3a3a;
}
.footprint-mode-night .footprint-timeline-name {
    color: #aaa;
}
.footprint-mode-night .footprint-toolbar input,
.footprint-mode-night .footprint-toolbar select {
    background: #333;
    border-color: #555;
    color: #ddd;
}
.footprint-mode-night .leaflet-control-scale-line {
    color: #ccc;
    border-color: rgba(255,255,255,0.4);
}
.footprint-mode-night .mapboxgl-ctrl-attrib {
    background: rgba(30,30,30,0.7) !important;
}
.footprint-mode-night .mapboxgl-popup-content {
    background: #2a2a2a !important;
    color: #ddd !important;
}
.footprint-mode-night .mapboxgl-popup-tip {
    border-top-color: #2a2a2a !important;
}

/* === 自动模式（跟随网站主题） === */
html.dark .footprint-mode-auto .footprint-map-container,
html.dark-color .footprint-mode-auto .footprint-map-container,
body.dark .footprint-mode-auto .footprint-map-container,
body.dark-color .footprint-mode-auto .footprint-map-container,
html[data-theme="dark"] .footprint-mode-auto .footprint-map-container {
    border-color: #3a3a3a;
}
html.dark .footprint-mode-auto .footprint-map-stats,
html.dark-color .footprint-mode-auto .footprint-map-stats,
body.dark .footprint-mode-auto .footprint-map-stats,
body.dark-color .footprint-mode-auto .footprint-map-stats,
html[data-theme="dark"] .footprint-mode-auto .footprint-map-stats {
    color: #aaa;
}
html.dark .footprint-mode-auto .footprint-map-tip,
html.dark-color .footprint-mode-auto .footprint-map-tip,
body.dark .footprint-mode-auto .footprint-map-tip,
body.dark-color .footprint-mode-auto .footprint-map-tip,
html[data-theme="dark"] .footprint-mode-auto .footprint-map-tip {
    color: #777;
}
html.dark .footprint-mode-auto .footprint-popup-name,
html.dark-color .footprint-mode-auto .footprint-popup-name,
body.dark .footprint-mode-auto .footprint-popup-name,
body.dark-color .footprint-mode-auto .footprint-popup-name,
html[data-theme="dark"] .footprint-mode-auto .footprint-popup-name {
    color: #ddd;
}
html.dark .footprint-mode-auto .footprint-popup-desc,
html.dark-color .footprint-mode-auto .footprint-popup-desc,
body.dark .footprint-mode-auto .footprint-popup-desc,
body.dark-color .footprint-mode-auto .footprint-popup-desc,
html[data-theme="dark"] .footprint-mode-auto .footprint-popup-desc {
    color: #aaa;
}
html.dark .footprint-mode-auto .footprint-popup-image-caption,
html.dark-color .footprint-mode-auto .footprint-popup-image-caption,
body.dark .footprint-mode-auto .footprint-popup-image-caption,
body.dark-color .footprint-mode-auto .footprint-popup-image-caption,
html[data-theme="dark"] .footprint-mode-auto .footprint-popup-image-caption {
    color: #888;
}
html.dark .footprint-mode-auto .leaflet-container,
html.dark-color .footprint-mode-auto .leaflet-container,
body.dark .footprint-mode-auto .leaflet-container,
body.dark-color .footprint-mode-auto .leaflet-container,
html[data-theme="dark"] .footprint-mode-auto .leaflet-container {
    background: #152030;
}
html.dark .footprint-mode-auto .leaflet-tile-pane,
html.dark-color .footprint-mode-auto .leaflet-tile-pane,
body.dark .footprint-mode-auto .leaflet-tile-pane,
body.dark-color .footprint-mode-auto .leaflet-tile-pane,
html[data-theme="dark"] .footprint-mode-auto .leaflet-tile-pane {
    filter: invert(1) hue-rotate(180deg) brightness(0.92) contrast(0.92) saturate(0.85);
}
html.dark .footprint-mode-auto .leaflet-control-attribution,
html.dark-color .footprint-mode-auto .leaflet-control-attribution,
body.dark .footprint-mode-auto .leaflet-control-attribution,
body.dark-color .footprint-mode-auto .leaflet-control-attribution,
html[data-theme="dark"] .footprint-mode-auto .leaflet-control-attribution {
    background: rgba(30,30,30,0.7) !important;
    color: #999 !important;
}
html.dark .footprint-mode-auto .leaflet-popup-content-wrapper,
html.dark-color .footprint-mode-auto .leaflet-popup-content-wrapper,
body.dark .footprint-mode-auto .leaflet-popup-content-wrapper,
body.dark-color .footprint-mode-auto .leaflet-popup-content-wrapper,
html[data-theme="dark"] .footprint-mode-auto .leaflet-popup-content-wrapper,
html.dark .footprint-mode-auto .leaflet-popup-tip,
html.dark-color .footprint-mode-auto .leaflet-popup-tip,
body.dark .footprint-mode-auto .leaflet-popup-tip,
body.dark-color .footprint-mode-auto .leaflet-popup-tip,
html[data-theme="dark"] .footprint-mode-auto .leaflet-popup-tip {
    background: #2a2a2a;
    color: #ddd;
}
html.dark .footprint-mode-auto .footprint-timeline,
html.dark-color .footprint-mode-auto .footprint-timeline,
body.dark .footprint-mode-auto .footprint-timeline,
body.dark-color .footprint-mode-auto .footprint-timeline,
html[data-theme="dark"] .footprint-mode-auto .footprint-timeline {
    border-color: #3a3a3a;
    background: #2a2a2a;
}
html.dark .footprint-mode-auto .footprint-timeline-title,
html.dark-color .footprint-mode-auto .footprint-timeline-title,
body.dark .footprint-mode-auto .footprint-timeline-title,
body.dark-color .footprint-mode-auto .footprint-timeline-title,
html[data-theme="dark"] .footprint-mode-auto .footprint-timeline-title {
    color: #ddd;
    border-bottom-color: #444;
}
html.dark .footprint-mode-auto .footprint-timeline-item:hover,
html.dark-color .footprint-mode-auto .footprint-timeline-item:hover,
body.dark .footprint-mode-auto .footprint-timeline-item:hover,
body.dark-color .footprint-mode-auto .footprint-timeline-item:hover,
html[data-theme="dark"] .footprint-mode-auto .footprint-timeline-item:hover {
    background: #3a3a3a;
}
html.dark .footprint-mode-auto .footprint-timeline-name,
html.dark-color .footprint-mode-auto .footprint-timeline-name,
body.dark .footprint-mode-auto .footprint-timeline-name,
body.dark-color .footprint-mode-auto .footprint-timeline-name,
html[data-theme="dark"] .footprint-mode-auto .footprint-timeline-name {
    color: #aaa;
}
html.dark .footprint-mode-auto .footprint-toolbar input,
html.dark-color .footprint-mode-auto .footprint-toolbar input,
body.dark .footprint-mode-auto .footprint-toolbar input,
body.dark-color .footprint-mode-auto .footprint-toolbar input,
html[data-theme="dark"] .footprint-mode-auto .footprint-toolbar input,
html.dark .footprint-mode-auto .footprint-toolbar select,
html.dark-color .footprint-mode-auto .footprint-toolbar select,
body.dark .footprint-mode-auto .footprint-toolbar select,
body.dark-color .footprint-mode-auto .footprint-toolbar select,
html[data-theme="dark"] .footprint-mode-auto .footprint-toolbar select {
    background: #333;
    border-color: #555;
    color: #ddd;
}
html.dark .footprint-mode-auto .leaflet-control-scale-line,
html.dark-color .footprint-mode-auto .leaflet-control-scale-line,
body.dark .footprint-mode-auto .leaflet-control-scale-line,
body.dark-color .footprint-mode-auto .leaflet-control-scale-line,
html[data-theme="dark"] .footprint-mode-auto .leaflet-control-scale-line {
    color: #ccc;
    border-color: rgba(255,255,255,0.4);
}

/* === 移动端响应式 === */
@media (max-width: 768px) {
    .footprint-map-body {
        flex-direction: column;
    }
    .footprint-timeline {
        width: 100% !important;
        max-height: 200px !important;
    }
    .footprint-toolbar input {
        min-width: 100%;
    }
}
</style>
HTML;
    }

    /**
     * 渲染 Leaflet 地图 JS
     */
    private static function renderLeafletJS($mapId, $placesEncoded, $tileProvider, $featuresJS)
    {
        $tileConfig = isset(self::TILE_PROVIDERS[$tileProvider]) ? self::TILE_PROVIDERS[$tileProvider] : self::TILE_PROVIDERS['osm'];
        $tileUrl = $tileConfig['url'];
        $tileSub = $tileConfig['sub'];
        $tileAttr = $tileConfig['attr'];

        return <<<HTML
<link rel="stylesheet" href="https://cdn.staticfile.net/leaflet/1.9.4/leaflet.css">
<script>
(function() {
    var mapId = '{$mapId}';
    var placesEncoded = '{$placesEncoded}';
    var tileUrl = '{$tileUrl}';
    var tileSub = '{$tileSub}';
    var tileAttr = '{$tileAttr}';
    var features = {$featuresJS};

    // 解码 Base64 混淆数据
    function decodePlaces(encoded) {
        if (features.cache) {
            try {
                var cached = localStorage.getItem('footprint_map_cache');
                if (cached === encoded) {
                    var cachedData = localStorage.getItem('footprint_map_data');
                    if (cachedData) return JSON.parse(cachedData);
                }
                // 数据变更：清除旧缓存，写入新缓存
                localStorage.removeItem('footprint_map_cache');
                localStorage.removeItem('footprint_map_data');
            } catch(e) {}
        } else {
            // 缓存已禁用：清除历史缓存
            try {
                localStorage.removeItem('footprint_map_cache');
                localStorage.removeItem('footprint_map_data');
            } catch(e) {}
        }
        try {
            var binary = atob(encoded);
            var json = decodeURIComponent(binary.split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            var data = JSON.parse(json);
            if (features.cache) {
                try {
                    localStorage.setItem('footprint_map_cache', encoded);
                    localStorage.setItem('footprint_map_data', json);
                } catch(e) {}
            }
            return data;
        } catch(e) {
            console.error('足迹数据解码失败', e);
            return [];
        }
    }

    var places = decodePlaces(placesEncoded);

    function loadJS(src, cb) {
        if (window.L) { cb(); return; }
        var s = document.createElement('script');
        s.src = src;
        s.onload = cb;
        s.onerror = function() { console.error('Leaflet 加载失败'); };
        document.head.appendChild(s);
    }

    // 年份智能格式化：2020 -> 2020年；2020.3 / 2020-03 / 2020/3 -> 2020年3月；其他原样返回
    function formatYear(yearStr) {
        if (!yearStr) return '';
        var s = String(yearStr).trim();
        var m = s.match(/^(\d{4})[.\/\-](\d{1,2})$/);
        if (m) {
            var y = m[1];
            var mo = parseInt(m[2], 10);
            if (mo >= 1 && mo <= 12) return y + '年' + mo + '月';
        }
        if (/^\d{4}$/.test(s)) return s + '年';
        return s;
    }

    function buildLinkText(place) {
        if (features.linkTextMode === 'url') return place.link;
        if (place.linkTitle) return place.linkTitle;
        return '查看相关链接';
    }

    function shouldFetchLinkTitle(place) {
        if (features.linkTextMode !== 'title' || place.linkTitle || !place.link) return false;
        try {
            return new URL(place.link, window.location.origin).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function hydrateLinkTitles(root) {
        if (features.linkTextMode !== 'title' || !window.fetch) return;
        var links = (root || document).querySelectorAll('.footprint-popup-link[data-title-url]');
        Array.prototype.forEach.call(links, function(link) {
            if (link.getAttribute('data-title-loaded')) return;
            link.setAttribute('data-title-loaded', '1');
            fetch(link.getAttribute('data-title-url'), {credentials: 'same-origin'})
                .then(function(res) { return res.ok ? res.text() : ''; })
                .then(function(html) {
                    if (!html) return;
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var title = doc.querySelector('title');
                    if (title && title.textContent.trim()) {
                        link.textContent = title.textContent.trim();
                    }
                })
                .catch(function() {});
        });
    }

    // 构建悬浮预览 HTML：只显示基础文字，不显示图片和链接
    function buildHoverHtml(place) {
        var alignClass = features.popupAlign === 'center' ? 'footprint-popup-center' : 'footprint-popup-left';
        var html = '<div class="' + alignClass + '">';
        html += '<div class="footprint-popup-name">' + place.name + '</div>';
        if (place.desc) html += '<div class="footprint-popup-desc">' + place.desc + '</div>';
        if (place.year) html += '<div class="footprint-popup-year">' + formatYear(place.year) + '</div>';
        html += '</div>';
        return html;
    }

    // 构建点击详情 HTML：只在存在图片或链接时显示
    function buildPopupHtml(place) {
        if (!place.image && !place.link) return '';
        var alignClass = features.popupAlign === 'center' ? 'footprint-popup-center' : 'footprint-popup-left';
        var html = '<div class="' + alignClass + '">';
        html += '<div class="footprint-popup-name">' + place.name + '</div>';
        if (place.desc) html += '<div class="footprint-popup-desc">' + place.desc + '</div>';
        if (place.year) html += '<div class="footprint-popup-year">' + formatYear(place.year) + '</div>';
        if (place.image) html += '<img class="footprint-popup-image" src="' + place.image + '" alt="' + place.name + '" onerror="this.style.display=\'none\'">';
        if (place.imageCaption) html += '<div class="footprint-popup-image-caption">' + place.imageCaption + '</div>';
        if (place.link) html += '<a class="footprint-popup-link" href="' + place.link + '" target="_blank" rel="noopener"' + (shouldFetchLinkTitle(place) ? ' data-title-url="' + place.link + '"' : '') + '>' + (shouldFetchLinkTitle(place) ? '' : buildLinkText(place)) + '</a>';
        html += '</div>';
        return html;
    }

    function initMap() {
        if (typeof L === 'undefined') {
            setTimeout(initMap, 50);
            return;
        }

        var isMobile = window.innerWidth < 768;
        var defaultColor = features.defaultMarkerColor || '#fd8888';
        var routeColor = features.routeColor || '#fd8888';
        var BottomPopup = L.Popup.extend({
            _updatePosition: function() {
                if (!this._map) return;
                var pos = this._map.latLngToLayerPoint(this._latlng),
                    offset = L.point(this.options.offset),
                    width = this._containerWidth || this._container.offsetWidth;
                L.DomUtil.setPosition(this._container, pos.add([offset.x - width / 2, offset.y]));
            }
        });

        var map = L.map(mapId, {
            center: [34, 104],
            zoom: 3.8,
            minZoom: 2,
            maxZoom: 18,
            zoomControl: false,
            scrollWheelZoom: features.mobileOpt && isMobile ? false : true,
            touchZoom: true,
            zoomSnap: 0.25,
            wheelZoomRate: 0.18,
            wheelDebounceTime: 120
        });

        // 瓦片图层
        L.tileLayer(tileUrl, {
            attribution: tileAttr,
            subdomains: tileSub,
            maxZoom: 18,
            detectRetina: true,
            crossOrigin: true
        }).addTo(map);

        // 比例尺
        L.control.scale({imperial: false, metric: true, position: 'bottomleft'}).addTo(map);

        // 足迹连线
        if (features.route) {
            var routeCoords = places.filter(function(p) {
                return p.lat && p.lng;
            }).map(function(p) {
                return [p.lat, p.lng];
            });
            if (routeCoords.length > 1) {
                L.polyline(routeCoords, {
                    color: routeColor,
                    weight: 2,
                    opacity: 0.5,
                    dashArray: '5, 8'
                }).addTo(map);
            }
        }

        // 添加标记
        var markers = [];
        var allMarkers = [];

        places.forEach(function(place) {
            if (!place.lat || !place.lng) return;
            var markerColor = features.customIcon ? (place.color || defaultColor) : defaultColor;
            var marker = L.circleMarker([place.lat, place.lng], {
                radius: 7,
                fillColor: markerColor,
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.85
            }).addTo(map);

            // 悬停预览卡片（mouseover 展示, mouseout 自动消失，不显示图片和链接）
            marker.bindTooltip(buildHoverHtml(place), {
                permanent: false,
                direction: 'top',
                className: 'footprint-tooltip-card',
                offset: [0, -8],
                sticky: false
            });

            // 点击详情卡片：仅存在图片或链接时打开，显示在地点下方
            var popupHtml = buildPopupHtml(place);
            if (popupHtml) {
                marker.bindPopup(new BottomPopup({
                    maxWidth: 300,
                    minWidth: 180,
                    closeButton: false,
                    autoClose: true,
                    closeOnClick: true,
                    autoPan: true,
                    offset: [0, 10],
                    className: 'footprint-popup footprint-popup-below'
                }).setContent(popupHtml));
                marker.on('popupopen', function(e) {
                    hydrateLinkTitles(e.popup.getElement());
                });
            }

            // 悬停放大效果
            (function(m, c) {
                m.on('mouseover', function(e) {
                    e.target.setStyle({radius: 9, fillColor: c === defaultColor ? '#fd2020' : c});
                });
                m.on('mouseout', function(e) {
                    e.target.setStyle({radius: 7, fillColor: c});
                });
            })(marker, markerColor);

            markers.push(marker);
            allMarkers.push({marker: marker, place: place});
        });


        // 时间轴
        if (features.timeline) {
            var timelineEl = document.getElementById('timeline-' + mapId);
            if (timelineEl) {
                timelineEl.innerHTML = '<div class="footprint-timeline-title">足迹列表</div>';
                allMarkers.forEach(function(item, i) {
                    var el = document.createElement('div');
                    el.className = 'footprint-timeline-item';
                    el.innerHTML = '<span class="footprint-timeline-year">' + formatYear(item.place.year) + '</span>' +
                                   '<span class="footprint-timeline-name">' + item.place.name + '</span>';
                    el.addEventListener('click', function() {
                        map.setView([item.place.lat, item.place.lng], 10);
                        item.marker.fire('click');
                    });
                    timelineEl.appendChild(el);
                });
            }
        }

        // 搜索地点
        if (features.search) {
            var toolbar = document.getElementById('toolbar-' + mapId);
            if (toolbar) {
                var searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.placeholder = '搜索地点...';
                searchInput.addEventListener('input', function() {
                    var query = this.value.trim().toLowerCase();
                    var visibleMarkers = [];
                    allMarkers.forEach(function(item) {
                        var match = !query ||
                            item.place.name.toLowerCase().indexOf(query) !== -1 ||
                            (item.place.desc && item.place.desc.toLowerCase().indexOf(query) !== -1);
                        if (match) {
                            if (!map.hasLayer(item.marker)) item.marker.addTo(map);
                            visibleMarkers.push(item.marker);
                        } else {
                            if (map.hasLayer(item.marker)) map.removeLayer(item.marker);
                        }
                    });
                    if (query && visibleMarkers.length > 0) {
                        var group = L.featureGroup(visibleMarkers);
                        map.fitBounds(group.getBounds().pad(0.2));
                    }
                });
                toolbar.appendChild(searchInput);
            }
        }

        // 年份筛选
        if (features.yearFilter) {
            var toolbar = document.getElementById('toolbar-' + mapId);
            if (toolbar) {
                var yearSelect = document.createElement('select');
                var years = [];
                allMarkers.forEach(function(item) {
                    if (item.place.year) years.push(item.place.year);
                });
                years = years.filter(function(v, i, a) { return a.indexOf(v) === i; }).sort();

                var optAll = document.createElement('option');
                optAll.value = '';
                optAll.textContent = '全部年份';
                yearSelect.appendChild(optAll);
                years.forEach(function(y) {
                    var opt = document.createElement('option');
                    opt.value = y;
                    opt.textContent = formatYear(y);
                    yearSelect.appendChild(opt);
                });

                yearSelect.addEventListener('change', function() {
                    var year = this.value;
                    var visibleMarkers = [];
                    allMarkers.forEach(function(item) {
                        if (!year || item.place.year === year) {
                            if (!map.hasLayer(item.marker)) item.marker.addTo(map);
                            visibleMarkers.push(item.marker);
                        } else {
                            if (map.hasLayer(item.marker)) map.removeLayer(item.marker);
                        }
                    });
                    if (visibleMarkers.length > 0) {
                        var group = L.featureGroup(visibleMarkers);
                        map.fitBounds(group.getBounds().pad(0.2));
                    }
                });
                toolbar.appendChild(yearSelect);
            }
        }

        // 默认不强制改动初始视野
    }

    loadJS('https://cdn.staticfile.net/leaflet/1.9.4/leaflet.min.js', initMap);
})();
</script>
HTML;
    }

    /**
     * 渲染 Mapbox GL 地图 JS
     */
    private static function renderMapboxJS($mapId, $placesEncoded, $token, $theme, $featuresJS)
    {
        $token = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        if ($theme === 'night') {
            $mapStyle = 'mapbox://styles/mapbox/dark-v11';
        } else {
            $mapStyle = 'mapbox://styles/mapbox/streets-v12';
        }

        return <<<HTML
<link rel="stylesheet" href="https://cdn.staticfile.net/mapbox-gl/3.7.0/mapbox-gl.css">
<script>
(function() {
    var mapId = '{$mapId}';
    var placesEncoded = '{$placesEncoded}';
    var accessToken = '{$token}';
    var theme = '{$theme}';
    var mapStyle = '{$mapStyle}';
    var features = {$featuresJS};

    // 解码 Base64 混淆数据
    function decodePlaces(encoded) {
        if (features.cache) {
            try {
                var cached = localStorage.getItem('footprint_map_cache');
                if (cached === encoded) {
                    var cachedData = localStorage.getItem('footprint_map_data');
                    if (cachedData) return JSON.parse(cachedData);
                }
                // 数据变更：清除旧缓存，写入新缓存
                localStorage.removeItem('footprint_map_cache');
                localStorage.removeItem('footprint_map_data');
            } catch(e) {}
        } else {
            // 缓存已禁用：清除历史缓存
            try {
                localStorage.removeItem('footprint_map_cache');
                localStorage.removeItem('footprint_map_data');
            } catch(e) {}
        }
        try {
            var binary = atob(encoded);
            var json = decodeURIComponent(binary.split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            var data = JSON.parse(json);
            if (features.cache) {
                try {
                    localStorage.setItem('footprint_map_cache', encoded);
                    localStorage.setItem('footprint_map_data', json);
                } catch(e) {}
            }
            return data;
        } catch(e) {
            console.error('足迹数据解码失败', e);
            return [];
        }
    }

    var places = decodePlaces(placesEncoded);

    // 自动模式：检测网站主题
    function isDarkTheme() {
        var el = document.documentElement;
        var body = document.body;
        if (!el) return false;
        var darkClasses = ['dark', 'dark-color', 'dark-theme', 'night'];
        for (var i = 0; i < darkClasses.length; i++) {
            if (el.classList.contains(darkClasses[i])) return true;
            if (body && body.classList.contains(darkClasses[i])) return true;
        }
        if (el.getAttribute('data-theme') === 'dark') return true;
        if (el.getAttribute('data-color-scheme') === 'dark') return true;
        return false;
    }

    if (theme === 'auto' && isDarkTheme()) {
        mapStyle = 'mapbox://styles/mapbox/dark-v11';
    }

    function loadJS(src, cb) {
        if (window.mapboxgl) { cb(); return; }
        var s = document.createElement('script');
        s.src = src;
        s.onload = cb;
        s.onerror = function() { console.error('Mapbox GL 加载失败'); };
        document.head.appendChild(s);
    }

    // 年份智能格式化：2020 -> 2020年；2020.3 / 2020-03 / 2020/3 -> 2020年3月；其他原样返回
    function formatYear(yearStr) {
        if (!yearStr) return '';
        var s = String(yearStr).trim();
        var m = s.match(/^(\d{4})[.\/\-](\d{1,2})$/);
        if (m) {
            var y = m[1];
            var mo = parseInt(m[2], 10);
            if (mo >= 1 && mo <= 12) return y + '年' + mo + '月';
        }
        if (/^\d{4}$/.test(s)) return s + '年';
        return s;
    }

    function buildLinkText(place) {
        if (features.linkTextMode === 'url') return place.link;
        if (place.linkTitle) return place.linkTitle;
        return '查看相关链接';
    }

    function shouldFetchLinkTitle(place) {
        if (features.linkTextMode !== 'title' || place.linkTitle || !place.link) return false;
        try {
            return new URL(place.link, window.location.origin).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function hydrateLinkTitles(root) {
        if (features.linkTextMode !== 'title' || !window.fetch) return;
        var links = (root || document).querySelectorAll('.footprint-popup-link[data-title-url]');
        Array.prototype.forEach.call(links, function(link) {
            if (link.getAttribute('data-title-loaded')) return;
            link.setAttribute('data-title-loaded', '1');
            fetch(link.getAttribute('data-title-url'), {credentials: 'same-origin'})
                .then(function(res) { return res.ok ? res.text() : ''; })
                .then(function(html) {
                    if (!html) return;
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var title = doc.querySelector('title');
                    if (title && title.textContent.trim()) {
                        link.textContent = title.textContent.trim();
                    }
                })
                .catch(function() {});
        });
    }

    // 构建悬浮预览 HTML：只显示基础文字，不显示图片和链接
    function buildHoverHtml(place) {
        var alignClass = features.popupAlign === 'center' ? 'footprint-popup-center' : 'footprint-popup-left';
        var html = '<div class="' + alignClass + '">';
        html += '<div class="footprint-popup-name">' + place.name + '</div>';
        if (place.desc) html += '<div class="footprint-popup-desc">' + place.desc + '</div>';
        if (place.year) html += '<div class="footprint-popup-year">' + formatYear(place.year) + '</div>';
        html += '</div>';
        return html;
    }

    // 构建点击详情 HTML：只在存在图片或链接时显示
    function buildPopupHtml(place) {
        if (!place.image && !place.link) return '';
        var alignClass = features.popupAlign === 'center' ? 'footprint-popup-center' : 'footprint-popup-left';
        var html = '<div class="' + alignClass + '">';
        html += '<div class="footprint-popup-name">' + place.name + '</div>';
        if (place.desc) html += '<div class="footprint-popup-desc">' + place.desc + '</div>';
        if (place.year) html += '<div class="footprint-popup-year">' + formatYear(place.year) + '</div>';
        if (place.image) html += '<img class="footprint-popup-image" src="' + place.image + '" alt="' + place.name + '" onerror="this.style.display=\'none\'">';
        if (place.imageCaption) html += '<div class="footprint-popup-image-caption">' + place.imageCaption + '</div>';
        if (place.link) html += '<a class="footprint-popup-link" href="' + place.link + '" target="_blank" rel="noopener"' + (shouldFetchLinkTitle(place) ? ' data-title-url="' + place.link + '"' : '') + '>' + (shouldFetchLinkTitle(place) ? '' : buildLinkText(place)) + '</a>';
        html += '</div>';
        return html;
    }

    function initMap() {
        if (typeof mapboxgl === 'undefined') {
            setTimeout(initMap, 50);
            return;
        }

        mapboxgl.accessToken = accessToken;

        var isMobile = window.innerWidth < 768;
        var defaultColor = features.defaultMarkerColor || '#fd8888';
        var routeColor = features.routeColor || '#fd8888';

        var map = new mapboxgl.Map({
            container: mapId,
            style: mapStyle,
            center: [104, 34],
            zoom: 3.2,
            minZoom: 1,
            maxZoom: 18,
            scrollZoom: features.mobileOpt && isMobile ? false : true,
            dragRotate: false
        });

        // 比例尺（透明背景，文案居中通过 CSS 实现）
        map.addControl(new mapboxgl.ScaleControl({unit: 'metric'}), 'bottom-left');

        // 悬浮 Popup 和点击 Popup
        var hoverPopup = null;
        var clickPopup = null;

        map.on('load', function() {
            // 中文语言（内联实现 mapbox-gl-language）
            var layers = map.getStyle().layers;
            if (layers) {
                layers.forEach(function(layer) {
                    if (layer.type === 'symbol' && layer.layout && layer.layout['text-field']) {
                        try {
                            map.setLayoutProperty(layer.id, 'text-field', [
                                'coalesce',
                                ['get', 'name_zh-Hans'],
                                ['get', 'name_zh'],
                                ['get', 'name']
                            ]);
                        } catch(e) {}
                    }
                });
            }

            // 构建 GeoJSON
            var validPlaces = places.filter(function(p) { return p.lat && p.lng; });
            var featuresData = validPlaces.map(function(p) {
                return {
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [p.lng, p.lat] },
                    properties: {
                        name: p.name,
                        desc: p.desc || '',
                        year: p.year || '',
                        color: features.customIcon ? (p.color || defaultColor) : defaultColor,
                        image: p.image || '',
                        imageCaption: p.imageCaption || '',
                        link: p.link || '',
                        linkTitle: p.linkTitle || ''
                    }
                };
            });

            var geojson = { type: 'FeatureCollection', features: featuresData };

            // 足迹连线
            if (features.route && validPlaces.length > 1) {
                var routeCoords = validPlaces.map(function(p) { return [p.lng, p.lat]; });
                map.addSource('footprint-route', {
                    type: 'geojson',
                    data: {
                        type: 'Feature',
                        geometry: { type: 'LineString', coordinates: routeCoords },
                        properties: {}
                    }
                });
                map.addLayer({
                    id: 'footprint-route-line',
                    type: 'line',
                    source: 'footprint-route',
                    paint: {
                        'line-color': routeColor,
                        'line-width': 2,
                        'line-opacity': 0.5,
                        'line-dasharray': [2, 3]
                    }
                });
            }

            // 数据源
            map.addSource('footprint-places', { type: 'geojson', data: geojson });

            // 标记图层
            map.addLayer({
                id: 'footprint-markers',
                type: 'circle',
                source: 'footprint-places',
                paint: {
                    'circle-radius': 7,
                    'circle-color': features.customIcon ? ['get', 'color'] : defaultColor,
                    'circle-stroke-width': 2,
                    'circle-stroke-color': '#ffffff',
                    'circle-opacity': 0.85
                }
            });

            // 悬停标记图层
            map.addLayer({
                id: 'footprint-markers-hover',
                type: 'circle',
                source: 'footprint-places',
                paint: {
                    'circle-radius': 9,
                    'circle-color': '#fd2020',
                    'circle-stroke-width': 2,
                    'circle-stroke-color': '#ffffff',
                    'circle-opacity': 0.85
                },
                filter: ['==', 'name', '']
            });

            // 鼠标悬停：放大标记 + 显示悬浮卡片（mouseout 自动消失）
            map.on('mouseenter', 'footprint-markers', function(e) {
                map.getCanvas().style.cursor = 'pointer';
                var feature = e.features[0];
                map.setFilter('footprint-markers-hover', ['==', 'name', feature.properties.name]);

                // 如果点击卡片已打开，不显示悬浮卡片
                if (clickPopup) return;

                // 悬浮卡片（closeOnClick: false, 不影响点击行为）
                var coords = feature.geometry.coordinates.slice();
                hoverPopup = new mapboxgl.Popup({
                    maxWidth: 300,
                    closeButton: false,
                    closeOnClick: false,
                    autoPan: false,
                    offset: [0, -18]
                })
                    .setLngLat(coords)
                    .setHTML(buildHoverHtml({
                        name: feature.properties.name,
                        desc: feature.properties.desc,
                        year: feature.properties.year
                    }))
                    .addTo(map);
            });
            map.on('mouseleave', 'footprint-markers', function() {
                map.getCanvas().style.cursor = '';
                map.setFilter('footprint-markers-hover', ['==', 'name', '']);
                // 移走自动关闭悬浮卡片
                if (hoverPopup) {
                    hoverPopup.remove();
                    hoverPopup = null;
                }
            });

            // 点击：显示点击卡片（点击其他区域关闭）
            map.on('click', 'footprint-markers', function(e) {
                var feature = e.features[0];
                var coords = feature.geometry.coordinates.slice();

                // 关闭悬浮卡片
                if (hoverPopup) {
                    hoverPopup.remove();
                    hoverPopup = null;
                }

                var popupHtml = buildPopupHtml({
                    name: feature.properties.name,
                    desc: feature.properties.desc,
                    year: feature.properties.year,
                    image: feature.properties.image,
                    imageCaption: feature.properties.imageCaption,
                    link: feature.properties.link,
                    linkTitle: feature.properties.linkTitle
                });
                if (!popupHtml) return;

                // 关闭旧的点击卡片，打开新的
                if (clickPopup) clickPopup.remove();
                clickPopup = new mapboxgl.Popup({
                    maxWidth: 300,
                    closeButton: false,
                    closeOnClick: true,
                    autoPan: true,
                    anchor: 'bottom',
                    offset: [0, 12]
                })
                    .setLngLat(coords)
                    .setHTML(popupHtml)
                    .addTo(map);
                hydrateLinkTitles(clickPopup.getElement());
                clickPopup.on('close', function() { clickPopup = null; });
            });

            // 时间轴
            if (features.timeline) {
                var timelineEl = document.getElementById('timeline-' + mapId);
                if (timelineEl) {
                    timelineEl.innerHTML = '<div class="footprint-timeline-title">足迹列表</div>';
                    validPlaces.forEach(function(p, i) {
                        var el = document.createElement('div');
                        el.className = 'footprint-timeline-item';
                        el.innerHTML = '<span class="footprint-timeline-year">' + formatYear(p.year) + '</span>' +
                                       '<span class="footprint-timeline-name">' + p.name + '</span>';
                        el.addEventListener('click', function() {
                            map.flyTo({ center: [p.lng, p.lat], zoom: 10 });
                            if (hoverPopup) { hoverPopup.remove(); hoverPopup = null; }
                            if (clickPopup) clickPopup.remove();
                            var popupHtml = buildPopupHtml(p);
                            if (!popupHtml) return;
                            clickPopup = new mapboxgl.Popup({
                                maxWidth: 300,
                                closeButton: false,
                                closeOnClick: true,
                                anchor: 'bottom',
                                offset: [0, 12]
                            })
                                .setLngLat([p.lng, p.lat])
                                .setHTML(popupHtml)
                                .addTo(map);
                            hydrateLinkTitles(clickPopup.getElement());
                            clickPopup.on('close', function() { clickPopup = null; });
                        });
                        timelineEl.appendChild(el);
                    });
                }
            }

            // 搜索地点
            if (features.search) {
                var toolbar = document.getElementById('toolbar-' + mapId);
                if (toolbar) {
                    var searchInput = document.createElement('input');
                    searchInput.type = 'text';
                    searchInput.placeholder = '搜索地点...';
                    searchInput.addEventListener('input', function() {
                        var query = this.value.trim().toLowerCase();
                        var filtered = validPlaces.filter(function(p) {
                            return !query ||
                                p.name.toLowerCase().indexOf(query) !== -1 ||
                                (p.desc && p.desc.toLowerCase().indexOf(query) !== -1);
                        });
                        var filteredGeojson = {
                            type: 'FeatureCollection',
                            features: filtered.map(function(p) {
                                return {
                                    type: 'Feature',
                                    geometry: { type: 'Point', coordinates: [p.lng, p.lat] },
                                    properties: {
                                        name: p.name, desc: p.desc || '',
                                        year: p.year || '',
                                        color: features.customIcon ? (p.color || defaultColor) : defaultColor,
                                        image: p.image || '',
                                        imageCaption: p.imageCaption || '',
                                        link: p.link || '',
                                        linkTitle: p.linkTitle || ''
                                    }
                                };
                            })
                        };
                        map.getSource('footprint-places').setData(filteredGeojson);
                        if (query && filtered.length > 0) {
                            var bounds = filtered.reduce(function(b, f) {
                                return b.extend([f.lng, f.lat]);
                            }, new mapboxgl.LngLatBounds());
                            map.fitBounds(bounds, { padding: 60 });
                        }
                    });
                    toolbar.appendChild(searchInput);
                }
            }

            // 年份筛选
            if (features.yearFilter) {
                var toolbar = document.getElementById('toolbar-' + mapId);
                if (toolbar) {
                    var yearSelect = document.createElement('select');
                    var years = validPlaces.map(function(p) { return p.year; }).filter(Boolean);
                    years = years.filter(function(v, i, a) { return a.indexOf(v) === i; }).sort();

                    var optAll = document.createElement('option');
                    optAll.value = '';
                    optAll.textContent = '全部年份';
                    yearSelect.appendChild(optAll);
                    years.forEach(function(y) {
                        var opt = document.createElement('option');
                        opt.value = y;
                        opt.textContent = formatYear(y);
                        yearSelect.appendChild(opt);
                    });

                    yearSelect.addEventListener('change', function() {
                        var year = this.value;
                        var filtered = year ? validPlaces.filter(function(p) { return p.year === year; }) : validPlaces;
                        var filteredGeojson = {
                            type: 'FeatureCollection',
                            features: filtered.map(function(p) {
                                return {
                                    type: 'Feature',
                                    geometry: { type: 'Point', coordinates: [p.lng, p.lat] },
                                    properties: {
                                        name: p.name, desc: p.desc || '',
                                        year: p.year || '',
                                        color: features.customIcon ? (p.color || defaultColor) : defaultColor,
                                        image: p.image || '',
                                        imageCaption: p.imageCaption || '',
                                        link: p.link || '',
                                        linkTitle: p.linkTitle || ''
                                    }
                                };
                            })
                        };
                        map.getSource('footprint-places').setData(filteredGeojson);
                        if (filtered.length > 0) {
                            var bounds = filtered.reduce(function(b, f) {
                                return b.extend([f.lng, f.lat]);
                            }, new mapboxgl.LngLatBounds());
                            map.fitBounds(bounds, { padding: 60 });
                        }
                    });
                    toolbar.appendChild(yearSelect);
                }
            }

            // 默认不强制改动初始视野
        });
    }

    loadJS('https://cdn.staticfile.net/mapbox-gl/3.7.0/mapbox-gl.js', initMap);
})();
</script>
HTML;
    }
}

/**
 * 安全单选框（兼容旧版 Checkbox 数组值）
 */
class SafeRadio extends TypechoRadio
{
    protected function inputValue($value)
    {
        if (is_array($value)) {
            $value = in_array('1', $value) ? '1' : '0';
        }
        parent::inputValue($value);
    }
}

/**
 * 自定义表单 HTML 块元素
 */
class HtmlBlock extends \Typecho\Widget\Helper\Layout
{
    private $htmlContent;

    public function __construct($htmlContent)
    {
        parent::__construct('div');
        $this->htmlContent = $htmlContent;
    }

    public function render()
    {
        echo $this->htmlContent;
    }
}
