<?php

namespace DNADesign\ElementalSubsites\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use Page;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Subsites\Model\Subsite;

/**
 * @package elemental
 */
class ElementalSubsitePageExtension extends DataExtension
{
    protected $original_subsite_filter_state;

    /**
     * If the page is duplicated across subsites, copy the elements across too.
     *
     * @return Page The duplicated page
     */
    public function onAfterDuplicateToSubsite($originalPage)
    {
        /** @var ElementalArea $originalElementalArea */
        $originalElementalArea = $originalPage->getComponent('ElementalArea');

        $duplicateElementalArea = $originalElementalArea->duplicate(false);
        $duplicateElementalArea->write();

        $this->owner->ElementalAreaID = $duplicateElementalArea->ID;
        $this->owner->write();

        foreach ($originalElementalArea->Items() as $originalElement) {
            /** @var BaseElement $originalElement */
            $duplicateElement = $originalElement->duplicate(true);

            // manually set the ParentID of each element, so we don't get versioning issues
            DB::query(
                sprintf(
                    "UPDATE %s SET ParentID = %d WHERE ID = %d",
                    DataObject::getSchema()->tableName(BaseElement::class),
                    $duplicateElementalArea->ID,
                    $duplicateElement->ID
                )
            );
        }
    }

    /**
     * Extension hook {@see ElementalAreasExtension::requireDefaultRecords}
     *
     * Sets Subsite::$disable_subsite_filter to true allowing ElementalAreas
     * to be created for each Subsite page.
     *
     * Sets $original_subsite_filter_state to the $disable_subsite_filter
     * current state so we can switch back to it after we are done with
     * creating the ElementalAreas
     *
     * @return void
     */
    public function onBeforeRequireDefaultElementalRecords()
    {
        $this->original_subsite_filter_state = Subsite::$disable_subsite_filter;
        Subsite::disable_subsite_filter();
    }

    /**
     * Extension hook {@see ElementalAreasExtension::requireDefaultRecords}
     *
     * Sets Subsite::$disable_subsite_filter to it's original
     * state with $original_subsite_filter_state
     *
     * @return void
     */
    public function onAfterRequireDefaultElementalRecords()
    {
        Subsite::disable_subsite_filter($this->original_subsite_filter_state);
    }
}
