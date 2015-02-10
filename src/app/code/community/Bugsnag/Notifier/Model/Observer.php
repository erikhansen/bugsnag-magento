<?php

class Bugsnag_Notifier_Model_Observer
{
    protected static $DEFAULT_NOTIFY_SEVERITIES = "fatal,error";

    protected static $NOTIFIER = array(
        "name" => "Bugsnag Magento (Official)",
        "version" => "1.0.0",
        "url" => "https://bugsnag.com/notifiers/magento"
    );

    protected $client;
    protected $apiKey;
    protected $notifySeverities;
    protected $filterFields;

    public static function fireTestEvent($apiKey) {
        if (strlen($apiKey) != 32) {
            throw new Exception("Invalid length of the API key");
        }

        $client = new Bugsnag_Client($apiKey);
        $client->notifyError(
            "BugsnagTest",
            "Testing bugsnag",
            array("notifier" => self::$NOTIFIER)
        );
    }

    public function initBugsnag()
    {
        if (file_exists(Mage::getBaseDir('lib') . '/bugsnag-php/Autoload.php')) {
            require_once(Mage::getBaseDir('lib') . '/bugsnag-php/Autoload.php');
        } else {
            error_log("Bugsnag Error: Couldn't activate Bugsnag Error Monitoring due to missing Bugsnag PHP library!");
            return;
        }

        $this->apiKey = Mage::getStoreConfig("dev/Bugsnag_Notifier/apiKey");
        $this->notifySeverities = Mage::getStoreConfig("dev/Bugsnag_Notifier/severites");
        $this->filterFields = Mage::getStoreConfig("dev/Bugsnag_Notifier/filterFiels");

        // Activate the bugsnag client
        if (!empty($this->apiKey)) {
            $this->client = new Bugsnag_Client($this->apiKey);

            $this->client->setReleaseStage($this->releaseStage())
                         ->setErrorReportingLevel($this->errorReportingLevel())
                         ->setFilters($this->filterFields());

            $this->client->setNotifier(self::$NOTIFIER);

            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $this->addUserTab();
            }

            set_error_handler(array($this->client, "errorHandler"));
            set_exception_handler(array($this->client, "exceptionHandler"));
        }
    }

    protected function addUserTab()
    {
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $this->client->setUser(array(
            'id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'created_at' => $customer->getCreatedAt(),
            'first_name' => $customer->getFirstname(),
            'last_name' => $customer->getLastname()
        ));
    }

    protected function releaseStage()
    {
        return Mage::getIsDeveloperMode() ? "development" : "production";
    }

    protected function errorReportingLevel()
    {
        if (empty($this->notifySeverities)) {
            $notifySeverities = "fatal,error";
        } else {
            $notifySeverities = $this->notifySeverities;
        }

        $level = 0;
        $severities = explode(",", $notifySeverities);

        foreach($severities as $severity) {
            $level |= Bugsnag_ErrorTypes::getLevelsForSeverity($severity);
        }

        return $level;
    }

    protected function filterFields()
    {
        return array_map('trim', explode("\n", $this->filterFields));
    }

}