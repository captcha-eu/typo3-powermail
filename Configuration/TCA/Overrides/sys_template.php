<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined("TYPO3") || die();

ExtensionManagementUtility::addStaticFile(
  "captchaeu",
  "Configuration/TypoScript",
  "Captcha.eu styles"
);
