<?php
namespace Axllent\MetaEditor;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\View\Requirements;
use TractorCow\Fluent\Model\Locale;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use Goldfinch\Seo\Forms\GridField\MetaEditorSEOColumn;
use Axllent\MetaEditor\Forms\MetaEditorPageColumn;
use Axllent\MetaEditor\Forms\MetaEditorTitleColumn;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Axllent\MetaEditor\Forms\MetaEditorPageLinkColumn;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use Axllent\MetaEditor\Forms\GridField\GridFieldLevelup;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use Axllent\MetaEditor\Forms\MetaEditorDescriptionColumn;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use Goldfinch\Seo\Forms\GridField\GridFieldEditNoInlineButton;

class MetaEditor extends ModelAdmin
{

    /**
     * Meta title field
     *
     * @config string
     */
    private static $meta_title_field = 'Title';

    /**
     * Meta title minimum length
     *
     * @var int
     */
    private static $meta_title_min_length = 20;

    /**
     * Meta title maximum length
     *
     * @var int
     */
    private static $meta_title_max_length = 70;

    /**
     * Meta description field
     *
     * @config string
     */
    private static $meta_description_field = 'MetaDescription';

    /**
     * Meta description field minimum length
     *
     * @var int
     */
    private static $meta_description_min_length = 100;

    /**
     * Meta description field maximum length
     *
     * @var int
     */
    private static $meta_description_max_length = 200;

    /**
     * Non-editable pages (includes all classes extending these)
     *
     * @config array
     */
    private static $non_editable_page_types = [
        'SilverStripe\\CMS\\Model\\RedirectorPage',
        'SilverStripe\\CMS\\Model\\VirtualPage',
    ];

    /**
     * Hidden pages (includes all classes extending these)
     * Note that these (or their children) are not displayed
     *
     * @config array
     */
    private static $hidden_page_types = [
        'SilverStripe\\ErrorPage\\ErrorPage',
    ];

    /**
     * CMS menu title
     *
     * @var string
     */
    private static $menu_title = 'Meta editor';

    /**
     * CMS url segment
     *
     * @var string
     */
    private static $url_segment = 'meta-editor';

    /**
     * CMS menu icon
     *
     * @var string
     */
    private static $menu_icon = 'goldfinch/silverstripe-meta-editor: images/MetaEditor.svg';

    // set current tab
    protected $modelTab = SiteTree::class;

    /**
     * CMS managed modals
     *
     * @var array
     */
    private static $managed_models = [
        SiteTree::class,
    ];

    /**
     * Init
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        Requirements::css('goldfinch/silverstripe-meta-editor: css/meta-editor.css');
        Requirements::javascript('goldfinch/silverstripe-meta-editor: javascript/meta-editor.js');
    }

    /**
     * Get edit form
     *
     * @param int   $id     ID
     * @param array $fields Array
     *
     * @return mixed
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $gridFieldName = $this->sanitiseClassName($this->modelClass);

        $grid = $form->Fields()->dataFieldByName($gridFieldName);

        if ($grid) {
            $config = $grid->getConfig();
            $config->removeComponentsByType(GridFieldAddNewButton::class);
            $config->removeComponentsByType(GridFieldPrintButton::class);
            $config->removeComponentsByType(GridFieldEditButton::class);
            $config->removeComponentsByType(GridFieldExportButton::class);
            $config->removeComponentsByType(GridFieldImportButton::class);

            // $config->removeComponentsByType(GridFieldDeleteAction::class);
            // $config->removeComponentsByType(GridField_ActionMenu::class);
            // $config->removeComponentsByType(GridField_ActionMenuItem::class);

            $parent_id = $this->request->requestVar('ParentID') ?: 0;

            if ($parent_id) {
                $parent = SiteTree::get()->byID($parent_id);
                if ($parent) {
                    if ($parent->Parent()->exists()) {
                        $up_title = $parent->Parent()->MenuTitle;
                    } else {
                        $up_title = 'top level';
                    }
                    $uplink = new GridFieldLevelup();
                    $uplink->setContent('Back to ' . htmlspecialchars($up_title));
                    if ($parent->ParentID) {
                        $uplink->setLinkSpec('admin/meta-editor/?ParentID=' . $parent->ParentID);
                    } else {
                        $uplink->setLinkSpec('admin/meta-editor/');
                    }
                    $config->addComponent($uplink);
                }
            } elseif ($this->request->requestVar('action_search')) {
                $uplink = new GridFieldLevelup();
                $uplink->setContent('Back to page listing');
                $uplink->setLinkSpec('admin/meta-editor/');
                $config->addComponent($uplink);
            }

            $displayFields = [
                'MetaEditorPageColumn'        => 'Page',
                'MetaEditorTitleColumn'       => 'Meta Title',
                'MetaEditorDescriptionColumn' => 'Meta Description',
                // 'MetaEditorPageLinkColumn'    => '',
            ];

            if (class_exists(MetaEditorSEOColumn::class))
            {
                $displayFields['MetaEditorSEOColumn'] = 'SEO';
            }

            $config->getComponentByType(GridFieldDataColumns::class)
                ->setDisplayFields($displayFields);

            $config->addComponent(new MetaEditorPageColumn());
            $config->addComponent(new MetaEditorTitleColumn());
            $config->addComponent(new MetaEditorDescriptionColumn());

            if (class_exists(MetaEditorSEOColumn::class))
            {
                $config->addComponent(new MetaEditorSEOColumn());
                $config->addComponent(new GridFieldEditNoInlineButton());
            }
            // $config->addComponent(new MetaEditorPageLinkColumn());
        }

        return $form;
    }

    /**
     * Get the list for the GridField
     *
     * @return SS_List
     */
    public function getList()
    {
        $list = parent::getList();

        $parent_id = $this->request->requestVar('ParentID') ?: 0;

        $search_filter = false;

        $conf = $this->config();

        if (!empty($this->request->requestVar('Search'))) {
            $search        = $this->request->requestVar('Search');
            $search_filter = true;
            $list          = $list->filterAny(
                [
                    'MenuTitle' . ':PartialMatch'                   => $search,
                    $conf->meta_title_field . ':PartialMatch'       => $search,
                    $conf->meta_description_field . ':PartialMatch' => $search,
                ]
            );
        }

        if (!empty($this->request->requestVar('EmptyMetaDescriptions'))) {
            $search_filter = true;
            $list          = $list->where(
                $this->config()->meta_description_field . ' IS NULL'
            );
        }
        if (!empty($this->request->requestVar('PagesWithWarnings'))) {
            $search_filter = true;
            $list          = $list->filterByCallback(
                function ($item) {
                    if (!empty(MetaEditorTitleColumn::getErrors($item))
                        || !empty(MetaEditorDescriptionColumn::getErrors($item))
                    ) {
                        return true;
                    }

                    return false;
                }
            );
        }

        if ($this->config()->hidden_page_types) {
            $ignore = [];
            foreach ($this->config()->hidden_page_types as $class) {
                $subclasses = ClassInfo::getValidSubClasses($class);
                $ignore     = array_merge(array_keys($subclasses), $ignore);
            }
            if (!empty($ignore)) {
                // remove error pages etc
                $list = $list->exclude('ClassName', $ignore);
            }
        }

        if (!$search_filter) {
            $list = $list->filter('ParentID', $parent_id);
        }

        $fluent = Injector::inst()->get(SiteTree::class)
            ->hasExtension(FluentSiteTreeExtension::class) && Locale::get()->count();

        if ($fluent) {
            $list = $list->filterbyCallBack(
                function ($page) {
                    return $page->existsInLocale();
                }
            );
        }

        return $list->count() ? $list : SiteTree::get()->filter('ID', 0);
    }

    public function getManagedModelTabs()
    {
        $tabs = parent::getManagedModelTabs();
        $tabs = $tabs->sort('Title', 'DESC');

        // foreach ($tabs as $k => $tab) {
        //     if ($k === 0) {
        //         $tab->LinkOrCurrent = 'current';
        //     } else {
        //         $tab->LinkOrCurrent = 'link';
        //     }
        // }

        return $tabs;
    }
}
