<?php

namespace DNADesign\ElementalSubsites\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

/**
 * Make elements compatible with subsites
 * Apply this extension to BaseElement
 */
class ElementalSubsiteExtension extends DataExtension
{
    private static $has_one = [
        'Subsite' => Subsite::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        // add SubsiteID if Subsites is installed and Elemental has a subsite
        if (class_exists(Subsite::class)) {
            $fields->push(
                HiddenField::create('SubsiteID', null, SubsiteState::singleton()->getSubsiteId())
            );
        }
    }

    /**
     * Ensure the new block inherits the current subsite id
     *
     * @return void
     */
    public function onBeforeWrite()
    {
        if (class_exists(Subsite::class)) {
            $this->owner->SubsiteID = SubsiteState::singleton()->getSubsiteId();
        }
    }

    /**
     * Update any requests for elements to limit the results to the current site
     *
     * @param SQLSelect $query
     * @param DataQuery|null $dataQuery
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        if (!class_exists(Subsite::class)) {
            return;
        }

        if (Subsite::$disable_subsite_filter) {
            return;
        }

        if ($dataQuery && $dataQuery->getQueryParam('Subsite.filter') === false) {
            return;
        }

        if ($query->filtersOnID()) {
            return;
        }

        if (Subsite::$force_subsite) {
            $subsiteID = Subsite::$force_subsite;
        } else {
            $subsiteID = (int) SubsiteState::singleton()->getSubsiteId();
        }

        // Get the base table name
        $elementTableName = DataObject::getSchema()->baseDataTable(BaseElement::class);

        // The foreach is an ugly way of getting the first key :-)
        foreach ($query->getFrom() as $tableName => $info) {
            // The tableName should be Element or Element_Live...
            if (substr($tableName, 0, strlen($elementTableName)) === $elementTableName) {
                $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
                break;
            }
        }
    }
}
