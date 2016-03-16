<?php

namespace T3Monitor\T3monitoringClient;

use Exception;
use T3Monitor\T3monitoringClient\Client as ClientService;
use T3Monitor\T3monitoringClient\Provider\DataProviderInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

class Client
{

    /**
     * Entry point
     */
    public function run()
    {
        if (!$this->checkAccess()) {
            HttpUtility::setResponseCodeAndExit(HttpUtility::HTTP_STATUS_403);
        }

        $data = $this->collectData();
        echo json_encode($data);
    }

    /**
     * Collect data
     *
     * @return array
     */
    protected function collectData()
    {
        $data = array();
        $classes = (array)$GLOBALS['TYPO3_CONF_VARS']['EXT']['t3monitoring_client']['provider'];

        if (empty($classes)) {
            $data['error'] = 'No providers';
        } else {

            foreach ($classes as $class) {
                /** @var DataProviderInterface $call */
                $call = GeneralUtility::makeInstance($class);
                if (!$call instanceof DataProviderInterface) {
                    $data['error'] = sprintf('The class "%s" does not implements "%s"!', $call, $class);
                    return $data;
                }
                $data = $call->get($data);
            }
        }
        return $data;
    }

    /**
     * Check if access is allowed to the endpoint
     *
     * @return bool
     */
    protected function checkAccess()
    {
        $settings = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3monitoring_client']);

        try {
            // secret
            if (isset($settings['secret']) && !empty($settings['secret']) && strlen($settings['secret']) >= 5) {
                $secret = GeneralUtility::_GET('secret');
                if ($secret !== $settings['secret']) {
                    throw new Exception(sprintf('Secret wrong, provided was "%s"', $secret));
                }
            } else {
                throw new Exception('No secret or too small secret defined');
            }

            if (!isset($settings['allowedIps']) || empty($settings['allowedIps'])) {
                throw new Exception('No allowed ips defined');
            }
            $remoteIp = GeneralUtility::getIndpEnv('REMOTE_ADDR');
            if (!GeneralUtility::cmpIP($remoteIp, $settings['allowedIps'])) {
                throw new Exception(sprintf('IP comparison failed, remote IP: %s!', $remoteIp));
            }
        } catch (Exception $e) {
            if (isset($settings['enableDebugForErrors']) && (int)$settings['enableDebugForErrors'] === 1) {
                die('ERROR: ' . $e->getMessage());
            }
            return false;
        }

        return true;
    }

}

/** @var ClientService $client */
$client = GeneralUtility::makeInstance('T3Monitor\\T3monitoringClient\\Client');
$client->run();