<?php

namespace CleverReach\WordPress\IntegrationCore\BusinessLogic\Sync;

use CleverReach\WordPress\IntegrationCore\Infrastructure\Logger\Logger;

/**
 * Class RegisterEventHandlerTask
 *
 * @package CleverReach\WordPress\IntegrationCore\BusinessLogic\Sync
 */
class RegisterEventHandlerTask extends BaseSyncTask
{
    const RECEIVER_EVENT = 'receiver';
    const FORM_EVENT = 'form';
    const GROUP_DELETED_EVENT = 'group.deleted';

    /**
     * Runs task logic.
     *
     * @throws \CleverReach\WordPress\IntegrationCore\Infrastructure\Exceptions\InvalidConfigurationException
     * @throws \CleverReach\WordPress\IntegrationCore\Infrastructure\Utility\Exceptions\HttpRequestException
     */
    public function execute()
    {
        $this->reportProgress(5);
        $configService = $this->getConfigService();
        $eventHookParams = array(
            'url' => $configService->getCrEventHandlerURL(),
            'event' => self::RECEIVER_EVENT,
            'verify' => $configService->getCrEventHandlerVerificationToken(),
        );

        if (stripos($eventHookParams['url'], 'https://') === 0) {
            // Register receiver webhook
            $callToken = $this->getProxy()->registerEventHandler($eventHookParams);
            $configService->setCrEventHandlerCallToken($callToken);

            // Register group deleted webhook
            $eventHookParams['event'] = self::GROUP_DELETED_EVENT;
            $callToken = $this->getProxy()->registerEventHandler($eventHookParams);
            $configService->setCrGroupDeletedEventHandlerCallToken($callToken);

            // Register forms webhook
            if ($configService->isFormSyncEnabled()) {
                $eventHookParams['event'] = self::FORM_EVENT;
                $callToken = $this->getProxy()->registerEventHandler($eventHookParams);
                $configService->setCrFormEventHandlerCallToken($callToken);
            }
        } else {
            Logger::logWarning('Cannot register CleverReach event hook for non-HTTPS domains.');
        }

        $this->reportProgress(100);
    }
}
