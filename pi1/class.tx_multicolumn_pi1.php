<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010 snowflake productions GmbH
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
class tx_multicolumn_pi1 extends tx_multicolumn_pi_base
{
    public $prefixId = 'tx_multicolumn_pi1';        // Same as class name

    public $scriptRelPath = 'pi1/class.tx_multicolumn_pi1.php';    // Path to this script relative to the extension dir.

    public $extKey = 'multicolumn';    // The extension key.

    public $pi_checkCHash = true;

    /**
     * Current cObj data
     *
     * @var        array
     */
    protected $currentCobjData;

    /**
     * Current cObjrecord string eg. tt_content:23
     *
     * @var        string
     */
    protected $currentCobjRecordString;

    /**
     * Incremented in parent cObj->RECORDS
     * and cObj->CONTENT before each record rendering.
     *
     * @var        int
     */
    protected $currentCobjParentRecordNumber;

    /**
     * Instance of tx_multicolumn_flexform
     *
     * @var        tx_multicolumn_flexform
     */
    protected $flex;

    /**
     * Layout configuration array from ts / flexform
     *
     * @var        array
     */
    protected $layoutConfiguration;

    /**
     * Layout configuration array from ts / flexform with option split
     *
     * @var        array
     */
    protected $layoutConfigurationSplited;

    /**
     * multicolumn uid
     *
     * @var        int
     */
    protected $multicolumnContainerUid;

    /**
     * Is effect box
     *
     * @var        int
     */
    protected $isEffectBox;

    /**
     * Effect configuration array from ts / flexform
     *
     * @var        array
     */
    protected $effectConfiguration;

    /**
     * maxWidth before
     *
     * @var        int
     */
    protected $TSFEmaxWidthBefore;

    /** @var string[] */
    protected $llPrefixed;

    /**
     * The main method of the PlugIn
     *
     * @param    string $content : The PlugIn content
     * @param    array $conf : The PlugIn configuration
     *
     * @return   string The content that is displayed on the website
     */
    public function main($content, $conf)
    {
        $this->init($content, $conf);
        // typoscript is not included
        if (!$this->conf['includeFromStatic']) {
            return $this->showFlashMessage($this->llPrefixed['lll:error.typoscript.title'], $this->llPrefixed['lll:error.typoscript.message']);
        }

        $content = $this->layoutConfiguration['columns'] ? $this->renderMulticolumnView() : $this->renderEffectBoxView();

        return $content;
    }

    /**
     * Initalizes the plugin.
     *
     * @param    string $content : Content sent to plugin
     * @param    string[] $conf : Typoscript configuration array
     */
    protected function init($content, $conf)
    {
        $this->content = $content;
        $this->conf = $conf;
        $this->pi_loadLL();

        $this->currentCobjData = $this->cObj->data;
        $this->currentCobjParentRecordNumber = $this->cObj->parentRecordNumber;
        $this->currentCobjRecordString = $this->cObj->currentRecord;

        //fallback to default
        $LLkey = (!empty($this->LOCAL_LANG[$this->LLkey])) ? $this->LLkey : 'default';
        $this->llPrefixed = tx_multicolumn_div::prefixArray($this->LOCAL_LANG[$LLkey], 'lll:');
        $this->pi_setPiVarDefaults();

        // Check if sys_language_contentOL is set and take $this->cObj->data['_LOCALIZED_UID']
        if ($GLOBALS['TSFE']->sys_language_contentOL && $GLOBALS['TSFE']->sys_language_uid && $this->cObj->data['_LOCALIZED_UID']) {
            $this->multicolumnContainerUid = $this->cObj->data['_LOCALIZED_UID'];
            // take default uid from cObj->data
        } else {
            $this->multicolumnContainerUid = $this->cObj->data['uid'];
        }

        $this->flex = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_multicolumn_flexform', $this->cObj->data['pi_flexform']);
        $this->isEffectBox = ($this->flex->getFlexValue('preSetLayout', 'layoutKey') == 'effectBox.') ? true : false;
        // store current max width
        $this->TSFEmaxWidthBefore = isset($GLOBALS['TSFE']->register['maxImageWidth']) ? $GLOBALS['TSFE']->register['maxImageWidth'] : null;

        // effect view
        if ($this->isEffectBox) {
            $this->effectConfiguration = tx_multicolumn_div::getEffectConfiguration(null, $this->flex);
            if (!empty($this->effectConfiguration['options'])) {
                $name = 'mullticolumnEffectBox_' . $this->cObj->data['uid'];
                $code = 'var ' . $name . ' ={' . $this->effectConfiguration['options'] . '};';
                $GLOBALS['TSFE']->getPageRenderer()->addJsInlineCode($name, $code);
            }
            // js files
            if (is_array($this->effectConfiguration['jsFiles.'])) {
                $this->includeCssJsFiles($this->effectConfiguration['jsFiles.']);
            }
            // css files
            if (is_array($this->effectConfiguration['cssFiles.'])) {
                $this->includeCssJsFiles($this->effectConfiguration['cssFiles.']);
            }

            // default multicolumn view
        } else {
            $this->layoutConfiguration = tx_multicolumn_div::getLayoutConfiguration(null, $this->flex);

            //include layout css
            if (!empty($this->layoutConfiguration['layoutCss']) || !empty($this->layoutConfiguration['layoutCss.'])) {
                $files = is_array($this->layoutConfiguration['layoutCss.']) ? $this->layoutConfiguration['layoutCss.'] : ['layoutCss' => $this->layoutConfiguration['layoutCss']];
                $this->includeCssJsFiles($files);
            }

            // force equal height ?
            $config = tx_multicolumn_div::getTSConfig($GLOBALS['TSFE']->id, 'config');
            if (!empty($this->layoutConfiguration['makeEqualElementBoxHeight'])) {
                if (is_array($config['advancedLayouts.']['makeEqualElementBoxHeight.']['includeFiles.'])) {
                    $this->includeCssJsFiles($config['advancedLayouts.']['makeEqualElementBoxHeight.']['includeFiles.']);
                }
            }
            // force equal height for each column
            if (!empty($this->layoutConfiguration['makeEqualElementColumnHeight'])) {
                if (is_array($config['advancedLayouts.']['makeEqualElementColumnHeight.']['includeFiles.'])) {
                    $this->includeCssJsFiles($config['advancedLayouts.']['makeEqualElementColumnHeight.']['includeFiles.']);
                }
            }

            // do option split
            $this->layoutConfigurationSplited = $GLOBALS['TSFE']->tmpl->splitConfArray($this->layoutConfiguration, $this->layoutConfiguration['columns']);
        }
    }

    protected function renderMulticolumnView()
    {
        $listItemData = $this->buildColumnData();
        //append config from column 0 for global config container width
        $listData = $listItemData[0];
        $listData['content'] = $this->renderListItems('_NO_TABLE', 'column', $listItemData, $this->llPrefixed);
        $listData['makeEqualElementBoxHeight'] = $this->layoutConfiguration['makeEqualElementBoxHeight'];
        $listData['makeEqualElementColumnHeight'] = $this->layoutConfiguration['makeEqualElementColumnHeight'];

        return $this->renderItem('columnContainer', $listData);
    }

    protected function renderEffectBoxView()
    {
        $listData = $this->cObj->data;

        $columnWidth = !empty($this->effectConfiguration['effectBoxWidth']) ? $this->effectConfiguration['effectBoxWidth'] : $this->renderColumnWidth();
        $isColumnWidthInt = intval($columnWidth);
        // evalute column width from css string
        if (empty($isColumnWidthInt)) {
            $matches = [];
            preg_match('/width?\s*:([0-9]*)/', $columnWidth, $matches);
            $columnWidth = $matches[1];
        }

        $GLOBALS['TSFE']->register['maxImageWidth'] = !empty($columnWidth) ? $columnWidth : $GLOBALS['TSFE']->register['maxImageWidth'];

        $contentElements = tx_multicolumn_db::getContentElementsFromContainer($columnData['colPos'], $this->cObj->data['pid'], $this->multicolumnContainerUid, $this->cObj->data['sys_language_uid']);
        if (is_array($contentElements)) {
            $listeItemsArray = [
                'effect' => $this->effectConfiguration['effect'],
                'columnWidth' => $columnWidth ? ('width:' . $columnWidth . 'px;') : null,
            ];
            $listeItemsArray = \TYPO3\CMS\Core\Utility\GeneralUtility::array_merge($listeItemsArray, $this->llPrefixed);
            $listItemContent = $this->renderListItems('tt_content', 'effectBoxItems', $contentElements, $listeItemsArray);
        } else {
            $listItemContent = '';
        }

        $listData['columnWidth'] = $columnWidth;
        $listData['effect'] = $this->effectConfiguration['effect'];
        $listData['effectBoxClass'] = $this->effectConfiguration['effectBoxClass'];
        $listData['effectBoxItems'] = $listItemContent;
        $listData = \TYPO3\CMS\Core\Utility\GeneralUtility::array_merge($listData, $this->llPrefixed);

        $content = $this->renderItem('effectBox', $listData);
        $GLOBALS['TSFE']->register['maxImageWidth'] = $this->TSFEmaxWidthBefore;

        return $content;
    }

    /**
     * Gets the data for each column
     *
     * @return    array            column data
     */
    protected function buildColumnData()
    {
        $numberOfColumns = $this->layoutConfiguration['columns'];
        $columnContent = [];
        $disableImageShrink = $this->layoutConfiguration['disableImageShrink'] ? true : false;

        $columnNumber = 0;
        while ($columnNumber < $numberOfColumns) {
            $multicolumnColPos = tx_multicolumn_div::colPosStart + $columnNumber;

            $splitedColumnConf = $this->layoutConfigurationSplited[$columnNumber];
            $conf = array_merge($this->layoutConfiguration, $splitedColumnConf);

            $colPosMaxImageWidth = $this->renderColumnWidth();

            $columnData = $conf;
            $columnData['columnWidth'] = $conf['columnWidth'] ? $conf['columnWidth'] : round(100 / $numberOfColumns);

            if (empty($this->layoutConfiguration['disableAutomaticImageWidthCalculation'])) {
                // evaluate columnWidth in pixels
                if ($conf['containerMeasure'] == 'px' && $conf['containerWidth']) {
                    $columnData['columnWidthPixel'] = round($conf['containerWidth'] / $numberOfColumns);

                    // if columnWidth and column measure is set
                } elseif ($conf['columnMeasure'] == 'px' && $conf['columnWidth']) {
                    $columnData['columnWidthPixel'] = $conf['columnWidth'];

                    // if container width is set in percent (default 100%)
                } elseif ($colPosMaxImageWidth) {
                    $columnData['columnWidthPixel'] = tx_multicolumn_div::calculateMaxColumnWidth($columnData['columnWidth'], $colPosMaxImageWidth, $numberOfColumns);
                }

                // calculate total column padding width
                if ($columnData['columnPadding']) {
                    $columnData['columnPaddingTotalWidthPixel'] = tx_multicolumn_div::getPaddingTotalWidth($columnData['columnPadding']);
                }
                // do auto scale if requested
                $maxImageWidth = $disableImageShrink ? null : (isset($columnData['columnWidthPixel']) ? ($columnData['columnWidthPixel'] - $columnData['columnPaddingTotalWidthPixel']) : null);
            } else {
                $maxImageWidth = $colPosMaxImageWidth;
            }

            $columnData['colPos'] = $multicolumnColPos;
            $contentElements = tx_multicolumn_db::getContentElementsFromContainer($columnData['colPos'], $this->cObj->data['pid'], $this->multicolumnContainerUid, $this->cObj->data['sys_language_uid']);
            if ($contentElements) {
                $GLOBALS['TSFE']->register['maxImageWidth'] = $maxImageWidth;
                $GLOBALS['TSFE']->register['maxImageWidthInText'] = $maxImageWidth;

                $columnData['content'] = $this->renderListItems('tt_content', 'columnItem', $contentElements, $this->llPrefixed);
            }

            $columnContent[] = $columnData;
            $columnNumber++;
        }

        // restore maxWidth
        $GLOBALS['TSFE']->register['maxImageWidth'] = $this->TSFEmaxWidthBefore;

        return $columnContent;
    }

    /**
     * Evaluates the maxwidth of current column
     *
     * @param    string $confName Path to typoscript to render each element with
     * @param    array $recordsArray Array which contains elements (array) for typoscript rendering
     * @param    array $appendData Additinal data
     *
     * @return    string        All items rendered as a string
     */
    protected function renderColumnWidth()
    {
        $conf = is_array($this->layoutConfiguration) ? $this->layoutConfiguration : [];
        $colPosData = array_merge([
            'colPos' => $this->cObj->data['colPos'],
            'CType' => $this->cObj->data['CType'],
        ], $conf);

        return intval($this->renderItem('columnWidth', $colPosData));
    }
}

if (defined('TYPO3_MODE') && isset($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/multicolumn/pi1/class.tx_multicolumn_pi1.php'])) {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/multicolumn/pi1/class.tx_multicolumn_pi1.php']);
}
