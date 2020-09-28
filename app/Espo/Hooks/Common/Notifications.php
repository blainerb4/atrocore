<?php

namespace Espo\Hooks\Common;

use Espo\ORM\Entity;
use Espo\Core\Utils\Util;

class Notifications extends \Espo\Core\Hooks\Base
{
    public static $order = 10;

    protected $notifatorsHash = array();

    private $streamService;

    private $hasStreamCache = array();

    protected function getServiceFactory()
    {
        return $this->getContainer()->get('serviceFactory');
    }

    protected function checkHasStream($entityType)
    {
        if (!array_key_exists($entityType, $this->hasStreamCache)) {
            $this->hasStreamCache[$entityType] = $this->getMetadata()->get("scopes.{$entityType}.stream");
        }
        return $this->hasStreamCache[$entityType];
    }

    protected function getNotificator($entityType)
    {
        if (empty($this->notifatorsHash[$entityType])) {
            $normalizedName = Util::normilizeClassName($entityType);

            $className = '\\Espo\\Custom\\Notificators\\' . $normalizedName;
            if (!class_exists($className)) {
                $moduleName = $this->getMetadata()->getScopeModuleName($entityType);
                if ($moduleName) {
                    $className = '\\Espo\\Modules\\' . $moduleName . '\\Notificators\\' . $normalizedName;
                } else {
                    $className = '\\Espo\\Notificators\\' . $normalizedName;
                }
                if (!class_exists($className)) {
                    $className = '\\Espo\\Core\\Notificators\\Base';
                }
            }

            $notificator = new $className();
            $dependencies = $notificator->getDependencyList();
            foreach ($dependencies as $name) {
                $notificator->inject($name, $this->getContainer()->get($name));
            }

            $this->notifatorsHash[$entityType] = $notificator;
        }
        return $this->notifatorsHash[$entityType];
    }

    public function afterSave(Entity $entity, array $options = array())
    {
        if (!empty($options['silent']) || !empty($options['noNotifications'])) {
            return;
        }

        $entityType = $entity->getEntityType();

        if (!$this->checkHasStream($entityType) || $entity->hasLinkMultipleField('assignedUsers')) {
            if (in_array($entityType, $this->getConfig()->get('assignmentNotificationsEntityList', []))) {
                $notificator = $this->getNotificator($entityType);
                $notificator->process($entity);
            }
        }
    }

    public function beforeRemove(Entity $entity, array $options = array())
    {
        if (!empty($options['silent']) || !empty($options['noNotifications'])) {
            return;
        }

        $entityType = $entity->getEntityType();
        if ($this->checkHasStream($entityType)) {
            $followersData = $this->getStreamService()->getEntityFollowers($entity);
            foreach ($followersData['idList'] as $userId) {
                if ($userId === $this->getUser()->id) {
                    continue;
                }
                $notification = $this->getEntityManager()->getEntity('Notification');
                $notification->set(array(
                    'userId' => $userId,
                    'type' => 'EntityRemoved',
                    'data' => array(
                        'entityType' => $entity->getEntityType(),
                        'entityId' => $entity->id,
                        'entityName' => $entity->get('name'),
                        'userId' => $this->getUser()->id,
                        'userName' => $this->getUser()->get('name')
                    )
                ));
                $this->getEntityManager()->saveEntity($notification);
            }
        }
    }

    public function afterRemove(Entity $entity)
    {
        $query = $this->getEntityManager()->getQuery();
        $sql = "
            DELETE FROM `notification`
            WHERE
                (related_id = ".$query->quote($entity->id)." AND related_type = ".$query->quote($entity->getEntityType()) .")
                OR
                (related_parent_id = ".$query->quote($entity->id)." AND related_parent_type = ".$query->quote($entity->getEntityType()) .")
        ";
        $this->getEntityManager()->getPDO()->query($sql);
    }

    protected function getStreamService()
    {
        if (empty($this->streamService)) {
            $this->streamService = $this->getServiceFactory()->create('Stream');
        }
        return $this->streamService;
    }

}
