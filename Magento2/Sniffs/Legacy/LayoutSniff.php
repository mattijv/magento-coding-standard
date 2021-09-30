<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\Sniffs\Legacy;

use DOMDocument;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SimpleXMLElement;

/**
 * Test for obsolete nodes/attributes in layouts
 */
class LayoutSniff implements Sniff
{
    private const ERROR_CODE_XML = 'WrongXML';
    private const ERROR_CODE_NOT_ALLOWED = 'NotAllowed';
    private const ERROR_CODE_OBSOLETE = 'Obsolete';
    private const ERROR_CODE_OBSOLETE_CLASS = 'ObsoleteClass';
    private const ERROR_CODE_ATTRIBUTE_NOT_VALID = 'AttributeNotValid';
    private const ERROR_CODE_METHOD_NOT_ALLOWED = 'MethodNotAllowed';
    private const ERROR_CODE_HELPER_ATTRIBUTE_CHARACTER_NOT_ALLOWED = 'CharacterNotAllowedInAttribute';
    private const ERROR_CODE_HELPER_ATTRIBUTE_CHARACTER_EXPECTED = 'CharacterExpectedInAttribute';

    /**
     * List of obsolete references per handle
     *
     * @var array
     */
    private $obsoleteReferences = [
        'adminhtml_user_edit' => [
            'adminhtml.permissions.user.edit.tabs',
            'adminhtml.permission.user.edit.tabs',
            'adminhtml.permissions.user.edit',
            'adminhtml.permission.user.edit',
            'adminhtml.permissions.user.roles.grid.js',
            'adminhtml.permission.user.roles.grid.js',
            'adminhtml.permissions.user.edit.tab.roles',
            'adminhtml.permissions.user.edit.tab.roles.js',
        ],
        'adminhtml_user_role_index' => [
            'adminhtml.permission.role.index',
            'adminhtml.permissions.role.index',
            'adminhtml.permissions.role.grid',
        ],
        'adminhtml_user_role_rolegrid' => ['adminhtml.permission.role.grid', 'adminhtml.permissions.role.grid'],
        'adminhtml_user_role_editrole' => [
            'adminhtml.permissions.editroles',
            'adminhtml.permissions.tab.rolesedit',
            'adminhtml.permission.roles.users.grid.js',
            'adminhtml.permissions.roles.users.grid.js',
            'adminhtml.permission.role.buttons',
            'adminhtml.permissions.role.buttons',
            'adminhtml.permission.role.edit.gws',
        ],
        'adminhtml_user_role_editrolegrid' => [
            'adminhtml.permission.role.grid.user',
            'adminhtml.permissions.role.grid.user',
        ],
        'adminhtml_user_index' => ['adminhtml.permission.user.index', 'adminhtml.permissions.user.index'],
        'adminhtml_user_rolegrid' => [
            'adminhtml.permissions.user.rolegrid',
            'adminhtml.permission.user.rolegrid',
        ],
        'adminhtml_user_rolesgrid' => [
            'adminhtml.permissions.user.rolesgrid',
            'adminhtml.permission.user.rolesgrid',
        ],
    ];

    private $allowedActionNodeMethods = [
        'addBodyClass',
        'addButtons',
        'addColumnCountLayoutDepend',
        'addCrumb',
        'addDatabaseBlock',
        'addInputTypeTemplate',
        'addNotice',
        'addReportTypeOption',
        'addTab',
        'addTabAfter',
        'addText',
        'append',
        'removeTab',
        'setActive',
        'setAddressType',
        'setAfterCondition',
        'setAfterTotal',
        'setAtCall',
        'setAtCode',
        'setAtLabel',
        'setAuthenticationStartMode',
        'setBeforeCondition',
        'setBlockId',
        'setBugreportUrl',
        'setCanLoadExtJs',
        'setCanLoadRulesJs',
        'setCanLoadTinyMce',
        'setClassName',
        'setColClass',
        'setColumnCount',
        'setColumnsLimit',
        'setCssClass',
        'setDefaultFilter',
        'setDefaultStoreName',
        'setDestElementId',
        'setDisplayArea',
        'setDontDisplayContainer',
        'setEmptyGridMessage',
        'setEntityModelClass',
        'setFieldOption',
        'setFieldVisibility',
        'setFormCode',
        'setFormId',
        'setFormPrefix',
        'setGiftRegistryTemplate',
        'setGiftRegistryUrl',
        'setGridHtmlClass',
        'setGridHtmlCss',
        'setGridHtmlId',
        'setHeaderTitle',
        'setHideBalance',
        'setHideLink',
        'setHideRequiredNotice',
        'setHtmlClass',
        'setId',
        'setImageType',
        'setImgAlt',
        'setImgHeight',
        'setImgSrc',
        'setImgWidth',
        'setInList',
        'setInfoTemplate',
        'setIsCollapsed',
        'setIsDisabled',
        'setIsEnabled',
        'setIsGuestNote',
        'setIsHandle',
        'setIsLinkMode',
        'setIsPlaneMode',
        'setIsTitleHidden',
        'setIsViewCurrent',
        'setItemLimit',
        'setLabel',
        'setLabelProperties',
        'setLayoutCode',
        'setLinkUrl',
        'setListCollection',
        'setListModes',
        'setListOrders',
        'setMAPTemplate',
        'setMethodFormTemplate',
        'setMyClass',
        'setPageLayout',
        'setPageTitle',
        'setParentType',
        'setControllerPath',
        'setPosition',
        'setPositioned',
        'setRewardMessage',
        'setRewardQtyLimitationMessage',
        'setShouldPrepareInfoTabs',
        'setShowPart',
        'setSignupLabel',
        'setSourceField',
        'setStoreVarName',
        'setStrong',
        'setTemplate',
        'setText',
        'setThemeName',
        'setTierPriceTemplate',
        'setTitle',
        'setTitleClass',
        'setTitleId',
        'setToolbarBlockName',
        'setType',
        'setUseConfirm',
        'setValueProperties',
        'setViewAction',
        'setViewColumn',
        'setViewLabel',
        'setViewMode',
        'setWrapperClass',
        'unsetChild',
        'unsetChildren',
        'updateButton',
        'setIsProductListingContext',
    ];

    /**
     * @inheritdoc
     */
    public function register(): array
    {
        return [
            T_INLINE_HTML
        ];
    }

    /**
     * @inheritDoc
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($stackPtr > 0) {
            return;
        }

        $layout = simplexml_load_string($this->getFormattedXML($phpcsFile));

        if ($layout === false) {
            $phpcsFile->addError(
                sprintf(
                    "Couldn't parse contents of '%s', check that they are in valid XML format",
                    $phpcsFile->getFilename(),
                ),
                $stackPtr,
                self::ERROR_CODE_XML
            );
            return;
        }

        $this->testObsoleteReferences($layout, $phpcsFile);
        $this->testObsoleteAttributes($layout, $phpcsFile);
        $this->testHeadBlocks($layout, $phpcsFile);
        $this->testOutputAttribute($layout, $phpcsFile);
        $this->testHelperAttribute($layout, $phpcsFile);
        $this->testListText($layout, $phpcsFile);
        $this->testActionNodeMethods($layout, $phpcsFile);
    }

    /**
     * @param SimpleXMLElement $layout
     * @param File $phpcsFile
     */
    private function testObsoleteReferences(SimpleXMLElement $layout, File $phpcsFile): void
    {
        foreach ($layout as $handle) {
            if (!isset($this->_obsoleteReferences[$handle->getName()])) {
                continue;
            }
            foreach ($handle->xpath('reference') as $reference) {
                if (strpos((string)$reference['name'], $this->obsoleteReferences[$handle->getName()]) !== false) {
                    $phpcsFile->addError(
                        'The block being referenced is removed.',
                        dom_import_simplexml($reference)->getLineNo(),
                        self::ERROR_CODE_OBSOLETE
                    );
                }
            }
        }
    }

    /**
     * Format the incoming XML to avoid tags split into several lines.
     *
     * @param File $phpcsFile
     * @return false|string
     */
    private function getFormattedXML(File $phpcsFile)
    {
        $doc = new DomDocument('1.0');
        $doc->formatOutput = true;
        $doc->loadXML($phpcsFile->getTokensAsString(0, 999999));
        return $doc->saveXML();
    }

    /**
     * @param SimpleXMLElement $layout
     * @param File $phpcsFile
     */
    private function testHeadBlocks(SimpleXMLElement $layout, File $phpcsFile): void
    {
        $selectorHeadBlock = '(name()="block" or name()="referenceBlock") and ' .
            '(@name="head" or @name="convert_root_head" or @name="vde_head")';
        $elements = $layout->xpath(
            '//block[@class="Magento\Theme\Block\Html\Head\Css" ' .
            'or @class="Magento\Theme\Block\Html\Head\Link" ' .
            'or @class="Magento\Theme\Block\Html\Head\Script"]' .
            '/parent::*[not(' .
            $selectorHeadBlock .
            ')]');
        if (!empty($elements)) {
            $phpcsFile->addError(
                'Blocks \Magento\Theme\Block\Html\Head\{Css,Link,Script} ' .
                'are allowed within the "head" block only. ' .
                'Verify integrity of the nodes nesting.',
                dom_import_simplexml($elements[0])->getLineNo(),
                self::ERROR_CODE_NOT_ALLOWED
            );
        };
    }

    /**
     * @param SimpleXMLElement $layout
     * @param File $phpcsFile
     */
    private function testOutputAttribute(SimpleXMLElement $layout, File $phpcsFile): void
    {
        $elements = $layout->xpath('/layout//*[@output="toHtml"]');
        if (!empty($elements)) {
            $phpcsFile->addError(
                'output="toHtml" is obsolete. Use output="1"',
                dom_import_simplexml($elements[0])->getLineNo(),
                self::ERROR_CODE_OBSOLETE
            );
        };
    }

    /**
     * Tests the attributes of the top-level Layout Node.
     * Verifies there are no longer attributes of "parent" or "owner"
     *
     * @todo missing test
     * @param SimpleXMLElement $layout
     * @param File $phpcsFile
     */
    private function testObsoleteAttributes(SimpleXMLElement $layout, File $phpcsFile): void
    {
        $type = $layout['type'];
        $parent = $layout['parent'];
        $owner = $layout['owner'];

        if ((string)$type === 'page') {
            if ($parent) {
                $phpcsFile->addError(
                    'Attribute "parent" is not valid',
                    dom_import_simplexml($parent)->getLineNo(),
                    self::ERROR_CODE_ATTRIBUTE_NOT_VALID
                );
            }
        }
        if ((string)$type === 'fragment') {
            if ($owner) {
                $phpcsFile->addError(
                    'Attribute "owner" is not valid',
                    dom_import_simplexml($owner)->getLineNo(),
                    self::ERROR_CODE_ATTRIBUTE_NOT_VALID
                );
            }
        }
    }

    /**
     * Returns attribute value by attribute name
     *
     * @param string $name
     * @return string|null
     */
    private function getAttribute(SimpleXMLElement $element, string $name): string
    {
        $attrs = $element->attributes();
        return isset($attrs[$name]) ? (string)$attrs[$name] : '';
    }

    /**
     * @param SimpleXMLElement $layout
     * @param File $phpcsFile
     */
    private function testHelperAttribute(SimpleXMLElement $layout, File $phpcsFile): void
    {
        foreach ($layout->xpath('//*[@helper]') as $action) {
            if (strpos($this->getAttribute($action, 'helper'), '/') !== false) {
                $phpcsFile->addError(
                    "'helper' attribute contains '/'",
                    dom_import_simplexml($action)->getLineNo(),
                    self::ERROR_CODE_HELPER_ATTRIBUTE_CHARACTER_NOT_ALLOWED
                );
            }
            if (strpos($this->getAttribute($action, 'helper'), '::') === false) {
                $phpcsFile->addError(
                    "'helper' attribute does not contain '::'",
                    dom_import_simplexml($action)->getLineNo(),
                    self::ERROR_CODE_HELPER_ATTRIBUTE_CHARACTER_EXPECTED
                );
            }
        }
    }

    /**
     * @param SimpleXMLElement $layout
     * @param File $phpcsFile
     */
    private function testListText(SimpleXMLElement $layout, File $phpcsFile): void
    {
        $elements = $layout->xpath('/layout//block[@class="Magento\Framework\View\Element\Text\ListText"]');
        if (!empty($elements)) {
            $phpcsFile->addError(
                'The class \Magento\Framework\View\Element\Text\ListText' .
                ' is not supposed to be used in layout anymore.',
                dom_import_simplexml($elements[0])->getLineNo(),
                self::ERROR_CODE_OBSOLETE_CLASS
            );
        };
    }

    /**
     * @param SimpleXMLElement $layout
     * @param File $phpcsFile
     */
    private function testActionNodeMethods(SimpleXMLElement $layout, File $phpcsFile): void
    {
        $methodFilter = '@method!="' . implode('" and @method!="', $this->allowedActionNodeMethods) . '"';
        foreach ($layout->xpath('//action[' . $methodFilter . ']') as $node) {
            $attributes = $node->attributes();
            $phpcsFile->addError(
                sprintf(
                    'Call of method "%s" via layout instruction <action> is not allowed.',
                    $attributes['method']
                ),
                dom_import_simplexml($node)->getLineNo(),
                self::ERROR_CODE_METHOD_NOT_ALLOWED
            );
        }
    }
}
