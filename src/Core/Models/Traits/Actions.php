<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Actions as CoreActions;

/**
 * To be used by Coretik Model.
 * 
 * Provide a callable [$this, 'getActions']
 *  - arrayof ['actionName' => @param \Coretik\Core\Actions\ActionInterface]
 */
trait Actions
{
    use Hooks;

    protected function initializeActions()
    {
        $this->on('launch_actions', function () {
            if (!\did_action('init')) {
                \remove_action('init', [$this, 'handleActions']);
            }
            $this->handleActions();
        });

        \add_action('init', [$this, 'handleActions']);
    }

    public static function getActionKey()
    {
        return static::POST_TYPE . '-request';
    }

    public function handleActions()
    {
        $action_key = self::getActionKey();
        if (!isset($_REQUEST[$action_key])) {
            return false;
        }

        $actions = $this->getActions();
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
            $action->onError($e);
        }
    }
}
