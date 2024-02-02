# Silverstripe Elemental Subsites

__NOTE__: This module is no longer commercially supported in Silverstripe CMS 5 and it does not provide a CMS5-compatible version.

[![CI](https://github.com/dnadesign/silverstripe-elemental-subsites/actions/workflows/ci.yml/badge.svg)](https://github.com/dnadesign/silverstripe-elemental-subsites/actions/workflows/ci.yml)

This module adds [subsite](https://github.com/silverstripe/silverstripe-subsites) support for 
[elemental](https://github.com/dnadesign/silverstripe-elemental).

```yaml
ElementPage:
  extensions:
    - DNADesign\ElementalSubsites\Extensions\ElementalSubsitePageExtension

DNADesign\Elemental\Models\BaseElement:
  extensions:
    - DNADesign\ElementalSubsites\Extensions\ElementalSubsiteExtension
```
