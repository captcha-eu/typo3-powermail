<?php
declare(strict_types=1);
namespace CaptchaEU\Domain\Validator\SpamShield;

use In2code\Powermail\Domain\Model\Field;
use In2code\Powermail\Domain\Validator\SpamShield\AbstractMethod;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Class CaptchaEUMethod
 */
class CaptchaEUMethod extends AbstractMethod
{
    /**
     * @var string
     */
    protected $restKey = '';

    /**
     * Check if secret key is given and set it
     *
     * @return void
     * @throws \Exception
     */
    public function initialize(): void
    {
        if ($this->isFormWithCaptchaEUField()) {
          
            if (empty($this->configuration['restkey']) || $this->configuration['restkey'] === 'abcdef') {
                throw new \LogicException(
                    'No restkey given. Please add a secret key to TypoScript Constants',
                    1607014176
                );
            }
            $this->restKey = $this->configuration['restkey'];
            
        }
    }

    public function checkSolution($solution) {
        $ch = curl_init("https://www.captcha.eu/validate");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $solution);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Rest-Key: ' . $this->restKey));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
  
        $resultObject = json_decode($result);
        if ($resultObject->success) {
          return true;
        } else {
          return false;
        }
      }
  

    /**
     * @return bool true if spam recognized
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function spamCheck(): bool
    {

        if (!$this->isFormWithCaptchaEUField() || $this->isCaptchaCheckToSkip()) {
            return false;
        }

        // Fix: Check if captcha_at_solution exists before accessing it
        // On confirmation pages, the captcha token is not submitted again
        if (!isset($_POST["captcha_at_solution"])) {
            // No captcha token present - this could be:
            // 1. A legitimate confirmation page (already validated)
            // 2. An attacker bypassing the captcha field

            // SECURITY: Only allow missing token if we have proof of prior validation
            if ($this->hasValidatedCaptchaInSession()) {
                // Confirmed: This form was already validated in this session
                // Clear the session flag to prevent replay attacks
                $this->clearCaptchaValidationFromSession();
                return false;
            }

            // No prior validation found - treat as spam attempt
            return true;
        }

        $result = $this->checkSolution($_POST["captcha_at_solution"]);
        if(!$result) {
            return true;
        }

        // Captcha validated successfully - store in session for confirmation page
        $this->storeCaptchaValidationInSession();

        return false;
    }

    /**
     * Check if current form has a recaptcha field
     *
     * @return bool
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws Exception
     */
    protected function isFormWithCaptchaEUField(): bool
    {
        foreach ($this->mail->getForm()->getPages() as $page) {
            /** @var Field $field */
            foreach ($page->getFields() as $field) {
                if ($field->getType() === 'captchaeu') {
                    return true;
                }
            }
        }
        return false;
    }



    /**
     * Captcha check should be skipped on createAction if there was a confirmationAction where the captcha was
     * already checked before
     * Note: $this->flexForm is only available in powermail 3.9 or newer
     *
     * @return bool
     */
    protected function isCaptchaCheckToSkip(): bool
    {
        if (property_exists($this, 'flexForm')) {
            $confirmationActive = $this->flexForm['settings']['flexform']['main']['confirmation'] === '1';
            $actionName = $this->getActionName();
            $isCreateAction = $actionName === 'create' || $actionName === 'checkCreate';
            return $isCreateAction && $confirmationActive;
        }
        return false;
    }

    /**
     * @return string "confirmation" or "create" / "checkConfirmation" or "checkCreate"
     */
    protected function getActionName(): string
    {
      if (isset($GLOBALS['TYPO3_REQUEST'])) {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $getMergedWithPost = $request->getQueryParams('tx_powermail_pi1');
        \TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($getMergedWithPost, $request->getParsedBody()['tx_powermail_pi1']);
        return $pluginVariables['action'] ?? '';
      }
      return '';
    }

    /**
     * Check if captcha has been validated in this session for this form
     *
     * @return bool
     */
    protected function hasValidatedCaptchaInSession(): bool
    {
        $sessionKey = $this->getCaptchaSessionKey();
        $sessionData = $this->getFromSession($sessionKey);

        // Check if validation exists and is recent (within 30 minutes)
        if ($sessionData && is_array($sessionData)) {
            $timestamp = $sessionData['timestamp'] ?? 0;
            $validatedFormUid = $sessionData['formUid'] ?? 0;
            $currentFormUid = $this->mail->getForm()->getUid();

            // Validate session: correct form, recent timestamp
            if ($validatedFormUid === $currentFormUid &&
                (time() - $timestamp) < 1800) { // 30 minutes
                return true;
            }
        }

        return false;
    }

    /**
     * Store successful captcha validation in session
     *
     * @return void
     */
    protected function storeCaptchaValidationInSession(): void
    {
        $sessionKey = $this->getCaptchaSessionKey();
        $data = [
            'validated' => true,
            'timestamp' => time(),
            'formUid' => $this->mail->getForm()->getUid()
        ];
        $this->storeInSession($sessionKey, $data);
    }

    /**
     * Clear captcha validation from session after use
     *
     * @return void
     */
    protected function clearCaptchaValidationFromSession(): void
    {
        $sessionKey = $this->getCaptchaSessionKey();
        $this->removeFromSession($sessionKey);
    }

    /**
     * Get session key for captcha validation
     *
     * @return string
     */
    protected function getCaptchaSessionKey(): string
    {
        return 'tx_captchaeu_powermail_validated';
    }

    /**
     * Get frontend user authentication object
     *
     * @return FrontendUserAuthentication|null
     */
    protected function getFrontendUser(): ?FrontendUserAuthentication
    {
        // TYPO3 12+: Use request attribute
        if (isset($GLOBALS['TYPO3_REQUEST'])) {
            $frontendUser = $GLOBALS['TYPO3_REQUEST']->getAttribute('frontend.user');
            if ($frontendUser instanceof FrontendUserAuthentication) {
                return $frontendUser;
            }
        }

        // TYPO3 11: Use TSFE
        if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->fe_user instanceof FrontendUserAuthentication) {
            return $GLOBALS['TSFE']->fe_user;
        }

        return null;
    }

    /**
     * Store data in TYPO3 frontend user session
     *
     * @param string $key
     * @param mixed $data
     * @return void
     */
    protected function storeInSession(string $key, $data): void
    {
        $frontendUser = $this->getFrontendUser();
        if ($frontendUser) {
            $frontendUser->setKey('ses', $key, $data);
            $frontendUser->storeSessionData();
        }
    }

    /**
     * Get data from TYPO3 frontend user session
     *
     * @param string $key
     * @return mixed
     */
    protected function getFromSession(string $key)
    {
        $frontendUser = $this->getFrontendUser();
        if ($frontendUser) {
            return $frontendUser->getKey('ses', $key);
        }
        return null;
    }

    /**
     * Remove data from TYPO3 frontend user session
     *
     * @param string $key
     * @return void
     */
    protected function removeFromSession(string $key): void
    {
        $frontendUser = $this->getFrontendUser();
        if ($frontendUser) {
            $frontendUser->setKey('ses', $key, null);
            $frontendUser->storeSessionData();
        }
    }
}
