<?php
namespace TypechoPlugin\PageSidebarToggle;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 页面侧边栏开关插件
 *
 * 在新增或编辑页面时，可以选择该页面是否显示侧边栏。
 * 适用于大多数 Typecho 主题（MWordStar、facile、default 等）。
 *
 * @package PageSidebarToggle
 * @author yuege.
 * @version 1.0.0
 * @link https://beicb.top
 */
class Plugin implements PluginInterface
{
    /**
     * 自定义字段名
     */
    const FIELD_NAME = 'showSidebar';

    /**
     * 激活插件
     */
    public static function activate()
    {
        // 在页面编辑器中添加"侧边栏显示"选项（仅对页面生效）
        \Typecho\Plugin::factory('Widget\Contents\Page\Edit')->getDefaultFieldItems = __CLASS__ . '::pageFields';

        // 在前台页面注入 CSS，隐藏侧边栏
        \Typecho\Plugin::factory('Widget\Archive')->footer = __CLASS__ . '::injectCss';

        return _t('插件启用成功，现在可以在编辑页面时选择是否显示侧边栏。');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
    }

    /**
     * 插件配置面板
     *
     * @param \Typecho\Widget\Helper\Form $form
     */
    public static function config($form)
    {
    }

    /**
     * 个人用户配置面板
     *
     * @param \Typecho\Widget\Helper\Form $form
     */
    public static function personalConfig($form)
    {
    }

    /**
     * 为页面编辑添加"侧边栏显示"自定义字段
     * 该字段会自动保存为页面的自定义字段
     *
     * @param Layout $layout
     */
    public static function pageFields(Layout $layout)
    {
        $element = new Select(
            self::FIELD_NAME,
            array(
                '1' => _t('显示侧边栏'),
                '0' => _t('隐藏侧边栏')
            ),
            '1',
            _t('侧边栏显示'),
            _t('选择是否在此页面显示侧边栏，默认显示。')
        );
        $layout->addItem($element);
    }

    /**
     * 在前台页面底部注入 CSS
     * 当页面设置了"隐藏侧边栏"时，通过 CSS 隐藏侧边栏并展开内容区域
     *
     * @param \Widget\Archive $archive
     */
    public static function injectCss($archive)
    {
        // 仅对独立页面生效
        if (!isset($archive->parameter->type) || $archive->parameter->type !== 'page') {
            return;
        }

        // 读取页面的自定义字段（Config 类未实现 __isset，直接读取值判断）
        $showSidebar = $archive->fields->{self::FIELD_NAME};

        // 如果设置为隐藏侧边栏（值为 '0'），注入 CSS
        if ($showSidebar === '0' || $showSidebar === 0) {
            echo <<<HTML
<style>
/* PageSidebarToggle 插件 - 隐藏侧边栏 */
/* 隐藏侧边栏元素 - 适用于 MWordStar / facile / default 等主题 */
.sidebar,
#secondary,
#sidebar,
.sidebar-column,
.col-md-12.col-lg-4.sidebar,
.col-md-12.col-lg-4.col-sm-12.sidebar {
    display: none !important;
}

/* 展开内容区域 - MWordStar 主题 */
.col-md-12.col-lg-8.col-sm-12.page,
.col-md-12.col-lg-8.col-sm-12.page.content-area {
    flex: 0 0 100% !important;
    max-width: 100% !important;
    width: 100% !important;
}

/* 展开内容区域 - facile 主题 */
.col-xl-8.col-lg-8.post-page {
    flex: 0 0 100% !important;
    max-width: 100% !important;
    width: 100% !important;
}

/* 展开内容区域 - default 主题 */
.col-mb-12.col-8#main {
    width: 100% !important;
    max-width: 100% !important;
}
</style>
HTML;
        }
    }
}
