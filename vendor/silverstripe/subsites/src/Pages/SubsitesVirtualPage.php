<?php

namespace SilverStripe\Subsites\Pages;

use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Subsites\Forms\SubsitesTreeDropdownField;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;
use SilverStripe\View\ArrayData;

class SubsitesVirtualPage extends VirtualPage
{

    private static $table_name = 'SubsitesVirtualPage';

    private static $description = 'Displays the content of a page on another subsite';

    private static $db = [
        'CustomMetaTitle' => 'Varchar(255)',
        'CustomMetaKeywords' => 'Varchar(255)',
        'CustomMetaDescription' => 'Text',
        'CustomExtraMeta' => 'HTMLText'
    ];

    private static $non_virtual_fields = [
        'SubsiteID'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $subsites = DataObject::get(Subsite::class);
        if (!$subsites) {
            $subsites = new ArrayList();
        } else {
            $subsites = ArrayList::create($subsites->toArray());
        }

        $subsites->push(new ArrayData(['Title' => 'Main site', 'ID' => 0]));

        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'CopyContentFromID_SubsiteID',
                _t(__CLASS__ . '.SubsiteField', 'Subsite'),
                $subsites->map('ID', 'Title')
            )->addExtraClass('subsitestreedropdownfield-chooser no-change-track'),
            'CopyContentFromID'
        );

        // Setup the linking to the original page.
        $pageSelectionField = SubsitesTreeDropdownField::create(
            'CopyContentFromID',
            _t('SilverStripe\\CMS\\Model\\VirtualPage.CHOOSE', 'Linked Page'),
            SiteTree::class,
            'ID',
            'MenuTitle'
        );

        $fields->addFieldToTab(
            'Root.Main',
            TreeDropdownField::create('CopyContentFromID', 'Linked Page', SiteTree::class)
        );

        if (Controller::has_curr() && Controller::curr()->getRequest()) {
            $subsiteID = (int) Controller::curr()->getRequest()->requestVar('CopyContentFromID_SubsiteID');
            $pageSelectionField->setSubsiteID($subsiteID);
        }
        $fields->replaceField('CopyContentFromID', $pageSelectionField);

        // Create links back to the original object in the CMS
        if ($this->CopyContentFromID) {
            $editLink = Controller::join_links(
                CMSPageEditController::singleton()->Link('show'),
                $this->CopyContentFromID
            );

            $linkToContent = "
				<a class=\"cmsEditlink\" href=\"$editLink\">" .
                _t('SilverStripe\\CMS\\Model\\VirtualPage.EDITCONTENT', 'Click here to edit the content') .
                '</a>';
            $fields->removeByName('VirtualPageContentLinkLabel');
            $fields->addFieldToTab(
                'Root.Main',
                $linkToContentLabelField = LiteralField::create('VirtualPageContentLinkLabel', $linkToContent),
                'Title'
            );
        }


        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'CustomMetaTitle',
                $this->fieldLabel('CustomMetaTitle')
            )->setDescription(_t(__CLASS__ . '.OverrideNote', 'Overrides inherited value from the source')),
            'MetaTitle'
        );
        $fields->addFieldToTab(
            'Root.Main',
            TextareaField::create(
                'CustomMetaKeywords',
                $this->fieldLabel('CustomMetaKeywords')
            )->setDescription(_t(__CLASS__ . '.OverrideNote', 'Overrides inherited value from the source')),
            'MetaKeywords'
        );
        $fields->addFieldToTab(
            'Root.Main',
            TextareaField::create(
                'CustomMetaDescription',
                $this->fieldLabel('CustomMetaDescription')
            )->setDescription(_t(__CLASS__ . '.OverrideNote', 'Overrides inherited value from the source')),
            'MetaDescription'
        );
        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'CustomExtraMeta',
                $this->fieldLabel('CustomExtraMeta')
            )->setDescription(_t(__CLASS__ . '.OverrideNote', 'Overrides inherited value from the source')),
            'ExtraMeta'
        );

        return $fields;
    }

    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['CustomMetaTitle'] = _t('SilverStripe\\Subsites\\Model\\Subsite.CustomMetaTitle', 'Title');
        $labels['CustomMetaKeywords'] = _t(
            'SilverStripe\\Subsites\\Model\\Subsite.CustomMetaKeywords',
            'Keywords'
        );
        $labels['CustomMetaDescription'] = _t(
            'SilverStripe\\Subsites\\Model\\Subsite.CustomMetaDescription',
            'Description'
        );
        $labels['CustomExtraMeta'] = _t(
            'SilverStripe\\Subsites\\Model\\Subsite.CustomExtraMeta',
            'Custom Meta Tags'
        );

        return $labels;
    }

    public function getCopyContentFromID_SubsiteID()
    {
        if ($this->CopyContentFromID) {
            return (int) $this->CopyContentFrom()->SubsiteID;
        }
        return SubsiteState::singleton()->getSubsiteId();
    }

    public function getVirtualFields()
    {
        $fields = parent::getVirtualFields();
        foreach ($fields as $k => $v) {
            if ($v == 'SubsiteID') {
                unset($fields[$k]);
            }
        }

        foreach (self::$db as $field => $type) {
            if (in_array($field, $fields ?? [])) {
                unset($fields[array_search($field, $fields)]);
            }
        }

        return $fields;
    }

    public function syncLinkTracking()
    {
        $oldState = Subsite::$disable_subsite_filter;
        Subsite::$disable_subsite_filter = true;
        if ($this->CopyContentFromID) {
            $this->HasBrokenLink = DataObject::get_by_id(SiteTree::class, $this->CopyContentFromID) ? false : true;
        }
        Subsite::$disable_subsite_filter = $oldState;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->CustomMetaTitle) {
            $this->MetaTitle = $this->CustomMetaTitle;
        } else {
            $this->MetaTitle = $this->ContentSource()->MetaTitle ?: $this->MetaTitle;
        }
        if ($this->CustomMetaKeywords) {
            $this->MetaKeywords = $this->CustomMetaKeywords;
        } else {
            $this->MetaKeywords = $this->ContentSource()->MetaKeywords ?: $this->MetaKeywords;
        }
        if ($this->CustomMetaDescription) {
            $this->MetaDescription = $this->CustomMetaDescription;
        } else {
            $this->MetaDescription = $this->ContentSource()->MetaDescription ?: $this->MetaDescription;
        }
        if ($this->CustomExtraMeta) {
            $this->ExtraMeta = $this->CustomExtraMeta;
        } else {
            $this->ExtraMeta = $this->ContentSource()->ExtraMeta ?: $this->ExtraMeta;
        }
    }

    public function validURLSegment()
    {
        $isValid = parent::validURLSegment();

        // Veto the validation rules if its false. In this case, some logic
        // needs to be duplicated from parent to find out the exact reason the validation failed.
        if (!$isValid) {
            $filters = [
                'URLSegment' => $this->URLSegment,
                'ID:not' => $this->ID,
            ];

            if (Config::inst()->get(SiteTree::class, 'nested_urls')) {
                $filters['ParentID'] = $this->ParentID ?: 0;
            }

            $origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
            Subsite::disable_subsite_filter();
            $existingPage = SiteTree::get()->filter($filters)->first();
            Subsite::disable_subsite_filter($origDisableSubsiteFilter);
            $existingPageInSubsite = SiteTree::get()->filter($filters)->first();

            // If URL has been vetoed because of an existing page,
            // be more specific and allow same URLSegments in different subsites
            $isValid = !($existingPage && $existingPageInSubsite);
        }

        return $isValid;
    }
}
