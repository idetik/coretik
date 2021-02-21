<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Actions as CoreActions;

trait Actions
{
    protected static function hooksActions()
    {
        \add_action('init', [static::class, "handleActions"]);
    }

    public static function getActionKey()
    {
        return static::POST_TYPE . '-request';
    }

    public static function handleActions()
    {
        $action_key = self::getActionKey();
        if (!isset($_REQUEST[$action_key])) {
            return false;
        }

        $actions = self::getActions();
        if (!array_key_exists($_REQUEST[$action_key], $actions)) {
            return null;
        }
    
        $action = $actions[$_REQUEST[$action_key]];

        switch (true) {
            case $action instanceof CoreActions\ActionAdminInterface:
                if (!is_admin()) {
                    return;
                }
                break;
            case $action instanceof CoreActions\ActionFrontInterface:
                if (\is_admin()) {
                    return;
                }
                break;
            default:
                return null;
        }

        $required = $action->getRequired();
        if (!empty(array_diff($required, array_keys($_REQUEST)))) {
            $message = sprintf('Paramètre(s) requis: %s', implode(', ', array_diff($required, array_keys($_REQUEST))));
            $action->onError(new CoreActions\Exception($message));
            // if (\is_admin()) {
            //     static::addNotice($message, 'error');
            // } else {
            // }
            return;
        }

        $params = [];
        $params['action_key'] = $action_key;
        foreach ($required as $field) {
            $params[$field] = $_REQUEST[$field];
        }

        try {
            $action->run($params);
        } catch (CoreActions\Exception $e) {
            if (\is_admin()) {
                static::addNotice($e->getMessage(), 'error');
            } else {
                $action->onError($e);
            }
        }
    }
}
