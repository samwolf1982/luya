<?php

namespace luya\cms\widgets;

use luya\cms\menu\QueryIteratorFilter;
use luya\helpers\ArrayHelper;
use Yii;
use luya\base\Widget;
use luya\cms\menu\Item;
use yii\helpers\Html;

/**
 * Build a Navigation from top down or based on the given item.
 *
 * Build the navigation from top:
 *
 * ```php
 * Nav::widget();
 * ```
 *
 * Build the navigation for a given item (all subpages)
 *
 * ```php
 * Nav::widget(['startItem' => Yii::$app->menu->current]);
 * ```
 *
 * *Example with all options:*
 *
 * ```php
 *    'startItem' => Yii::$app->menu->home,
 *    'maxDepth' => 2,
 *    'linkActiveClass' => 'link-active',
 *    'itemActiveClass' => 'item-active',
 *    'listDepthClassPrefix' => 'list-depth-',
 *    'wrapperOptions' => [
 *        'tag' => 'nav',
 *        'class' => 'navigation'
 *    ],
 *    'itemOptions' => [
 *        'tag' => 'li',
 *        'class' => 'item depth-{{depth}} alias-{{alias}}'
 *    ],
 *    'linkOptions' => [
 *        'tag' => 'a',
 *        'class' => 'link depth-{{depth}} alias-{{alias}}'
 *    ],
 * ]
 * ```
 *
 * @property \luya\cms\menu\Item $startItem Get the start Item entry.
 * @author Marc Stampfli <kontakt@marcstampfli.guru>
 * @since 1.0.0
 */
class NavTree extends Widget
{
    /**
     * @var null|Item Generate submenus for all pages below you this menu Item
     */
    private $_startItem = null;

    /**
     * @var null|integer If set the depth of the menu will be limited
     */
    public $maxDepth = null;

    /**
     * @var null|string The class that should be set on the *active link*
     */
    public $linkActiveClass = null;

    /**
     * @var null|string The class that should be set on the *active item*
     */
    public $itemActiveClass = null;

    /**
     * @var string This prefix will be set in front of the depth number on the list class e.g. list-depth-2
     */
    public $listDepthClassPrefix = '';

    /**
     * @var null|array If set, a wrapper will be wrapped around the list
     * - tag: The tag for the wrapper, e.g. `nav`
     * - class: Class or classes for the wrapper
     *
     * You can set all possible html attributes as options
     */
    public $wrapperOptions = null;

    /**
     * @var array Options for the lists that are generated
     * - tag: The tag for the list, default is `ul`
     * - class: Class or classes for the list
     *
     * You can set all possible html attributes as options
     */
    public $listOptions = [];

    /**
     * @var array Options for the items that are generated
     * - tag: The tag for the item, default is `li`
     * - class: Class or classes for the item
     *
     * You can set all possible html attributes as options
     */
    public $itemOptions = [];

    /**
     * @var array Options for the links that are generated
     * - tag: The tag for the link, default is `a`
     * - class: Class or classes for the link
     *
     * You can set all possible html attributes as options
     * **Note: href and content will be set regardless of the options**
     */
    public $linkOptions = [];

    /**
     * @var null|string The list tag will be set during init
     */
    private $_listTag = null;

    /**
     * @var null|string The item tag will be set during init
     */
    private $_itemTag = null;

    /**
     * @var null|string The link tag will be set during init
     */
    private $_linkTag = null;

    /**
     * Init of the NavTree widget, gets the tags from the options that are used in the widget
     */
    public function init()
    {
        parent::init();

        $this->_listTag = ArrayHelper::remove($this->listOptions, 'tag', 'ul');
        $this->_itemTag = ArrayHelper::remove($this->itemOptions, 'tag', 'li');
        $this->_linkTag = ArrayHelper::remove($this->linkOptions, 'tag', 'a');
    }

    /**
     * @return string HTML representation of the generated menu
     */
    public function run()
    {
        $html = "";

        if($this->startItem === null) {
            $html = $this->buildList(Yii::$app->menu->find()->where(['container' => 'default', 'parent_nav_id' => 0])->all());
        } else if($this->startItem->hasChildren) {
            $this->buildList($this->startItem->children, 0);
        }

        if($this->wrapperOptions !== null) {
            $wrapperTag = ArrayHelper::remove($this->wrapperOptions, 'tag', 'nav');
            $html = Html::tag($wrapperTag, $html, $this->wrapperOptions);
        }

        return $html;
    }

    /**
     * Builds the list for the given Iterator and recursively calls itself to also generate the menu for all children
     *
     * @param QueryIteratorFilter $iterator The iterator used to build the list
     * @param int $i The counter that is used to set the list depth
     * @return string Part of the menu
     */
    private function buildList(QueryIteratorFilter $iterator, $i = 1)
    {
        // Abort if maxDepth is set & reached
        if($this->maxDepth !== null && $i >= $this->maxDepth) {
            return "";
        }

        // Add the listDepthClassPrefix manually
        $listOptions = $this->listOptions;

        if (!isset($listOptions['class'])) {
            $listOptions['class'] = "";
        }

        $listOptions['class'] .= " " . $this->listDepthClassPrefix . $i;

        // <ul>
        $html = Html::beginTag($this->_listTag, $listOptions);

        foreach ($iterator as $item) {
            $itemOptions = $this->itemOptions;
            $linkOptions = array_merge($this->linkOptions, ['href' => $item->link]);

            // Set the active classes if item is active
            if($item->isActive) {
                $itemOptions['class'] .= $this->itemActiveClass !== null ? ' ' . $this->itemActiveClass : '';
                $linkOptions['class'] .= $this->linkActiveClass !== null ? ' ' . $this->linkActiveClass : '';
            }

            // <li>
            $html .= Html::beginTag($this->_itemTag, $this->compileOption($item, $itemOptions));

            // <a></a>
            $html .= Html::tag($this->_linkTag, $item->title, $this->compileOption($item, $linkOptions));

            // Recursive iterate if item has Children
            if ($item->hasChildren) {
                $html .= $this->buildList($item->children, $i + 1);
            }

            // </li>
            $html .= Html::endTag($this->_itemTag);
        }

        // </ul>
        return $html . Html::endTag($this->_listTag);
    }

    /**
     * Replaces the placeholders, for example {{alias}}, with the value stored in $item
     * If the placeholder name isn't found as a property, it will be returned (e.g. alias)
     *
     * @param Item|null $item
     * @param array $options
     * @return array
     */
    private function compileOption(Item $item = null, array $options)
    {
        foreach ($options as $key => $option) {
            $options[$key] = preg_replace_callback('/{{([^}]*)}}/', function ($match) use ($item) {
                if ($item && $item->hasProperty($match[1])) {
                    $f = $match[1];
                    return $item->$f;
                }

                return 0;
            }, $option);
        }

        return $options;
    }

    /**
     * @param Item $item
     */
    public function setStartItem(Item $item)
    {
        $this->_startItem = $item;
    }

    /**
     * @return Item
     */
    public function getStartItem()
    {
        return $this->_startItem;
    }
}