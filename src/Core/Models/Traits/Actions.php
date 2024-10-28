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

    protected function initializeActions(): void
    {
        $this->on('launch_actions', function () {
            if (!\did_action('init')) {
                \remove_action('init', [$this, 'handleActions']);
            }
            $this->handleActions();
        });

        \add_action('init', [$this, 'handleActions']);
    }

    public function getActionKey(): string
    {
        return $this->name() . '-request';
    }

    public function handleActions(): void
    {
        $action_key = $this->getActionKey();
        if (!isset($_REQUEST[$action_key])) {
            return;
        }

        $request_action = $_REQUEST[$action_key];

        $actions = $this->getActions();
        if (!array_key_exists($request_action, $actions)) {
            return;
        }

        $action = $actions[$request_action];
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
                return;
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
            $params[$field] = \esc_attr($_REQUEST[$field]);
        }

        try {
            $action->run($params);
        } catch (CoreActions\Exception $e) {
            $action->onError($e);
        }
    }
}
