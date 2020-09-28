<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Treo\Core\Utils\Util;
use Treo\Core\EventManager\Event;

/**
 * Class FieldManagerController
 *
 * @author r.ratsun@gmail.com
 */
class FieldManagerController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforePostActionCreate(Event $event)
    {
        // is default value valid ?
        $this->isDefaultValueValid($event->getArgument('data')->type, $event->getArgument('data')->default);
    }

    /**
     * @param Event $event
     */
    public function beforePatchActionUpdate(Event $event)
    {
        $this->beforePostActionCreate($event);
    }

    /**
     * @param Event $event
     */
    public function beforePutActionUpdate(Event $event)
    {
        $this->beforePostActionCreate($event);
    }

    /**
     * @param Event $event
     */
    public function beforeDeleteActionDelete(Event $event)
    {
        // delete columns from DB
        $this->deleteColumns($event->getArgument('params')['scope'], $event->getArgument('params')['name']);
    }

    /**
     * Delete column(s) from DB
     *
     * @param string $scope
     * @param string $field
     */
    protected function deleteColumns(string $scope, string $field): void
    {
        // get field metadata
        $fields = $this
            ->getContainer()
            ->get('metadata')
            ->getFieldList($scope, $field);

        if (!empty($fields)) {
            // prepare table name
            $table = Util::toUnderScore($scope);

            foreach ($fields as $name => $row) {
                // prepare column
                $column = Util::toUnderScore($name);
                switch ($row['type']) {
                    case 'file':
                        $column .= '_id';
                        break;
                    case 'image':
                        $column .= '_id';
                        break;
                }

                try {
                    // execute SQL
                    $sth = $this
                        ->getEntityManager()
                        ->getPDO()
                        ->prepare("ALTER TABLE {$table} DROP COLUMN {$column};");
                    $sth->execute();
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * Is default value valid
     *
     * @param string $type
     * @param mixed  $default
     *
     * @return bool
     * @throws BadRequest
     */
    protected function isDefaultValueValid(string $type, $default): bool
    {
        // prepare types
        $types = ['text', 'textMultiLang', 'wysiwyg', 'wysiwygMultiLang'];

        if (in_array($type, $types) && is_string($default) && strpos($default, "'") !== false) {
            // prepare message
            $message = $this
                ->getLanguage()
                ->translate('defaultValidationFailed', 'messages', 'FieldManager');

            throw new BadRequest($message);
        }

        return true;
    }
}