<?php

namespace Oro\Component\Layout;

/**
 * Implements the layout manipulator which allows to perform manipulations in random order
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class DeferredLayoutManipulator implements DeferredLayoutManipulatorInterface
{
    /** The group name for add new items related actions */
    const GROUP_ADD = 1;

    /** The group name for remove items related actions */
    const GROUP_REMOVE = 2;

    /** The action name for add layout item */
    const ADD = 1;

    /** The action name for remove layout item */
    const REMOVE = 2;

    /** The action name for move layout item */
    const MOVE = 3;

    /** The action name for add the alias for the layout item */
    const ADD_ALIAS = 4;

    /** The action name for remove the alias for the layout item */
    const REMOVE_ALIAS = 5;

    /** The action name for add/update an option for the layout item */
    const SET_OPTION = 6;

    /** The action name for add additional value for an option for the layout item */
    const APPEND_OPTION = 7;

    /** The action name for remove a value from an option for the layout item */
    const SUBTRACT_OPTION = 8;

    /** The action name for replace one value with another value for an option for the layout item */
    const REPLACE_OPTION = 9;

    /** The action name for remove an option for the layout item */
    const REMOVE_OPTION = 10;

    /** The action name for change the block type for the layout item */
    const CHANGE_BLOCK_TYPE = 11;

    /** The action name for add the theme(s) to be used for rendering the layout item and its children */
    const SET_BLOCK_THEME = 12;

    /** @var LayoutRegistryInterface */
    protected $registry;

    /** @var RawLayoutBuilderInterface */
    protected $rawLayoutBuilder;

    /**
     * The list of all scheduled actions to be executed by applyChanges method
     *
     * @var array
     *
     * Example:
     *  [
     *      'add' => [ // add new items related actions:
     *          // add, move, addAlias, [set/append/subtract/replace/remove]Option
     *          ['add', ['root', null, 'root', [], null, false]],
     *          ['add', ['my_label', 'my_root', 'label', ['text' => 'test'], null, true]],
     *          ['addAlias', ['my_root', 'root']],
     *      ],
     *      'remove' => [ // remove items related actions: remove, removeAlias
     *          ['remove', ['my_label']],
     *          ['removeAlias', ['my_root']],
     *      ],
     *  ]
     */
    protected $actions = [];

    /** @var LayoutItem */
    protected $item;

    /** @var int */
    protected $addCounter = 0;

    /**
     * @param LayoutRegistryInterface   $registry
     * @param RawLayoutBuilderInterface $rawLayoutBuilder
     */
    public function __construct(
        LayoutRegistryInterface $registry,
        RawLayoutBuilderInterface $rawLayoutBuilder
    ) {
        $this->registry         = $registry;
        $this->rawLayoutBuilder = $rawLayoutBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function add(
        $id,
        $parentId,
        $blockType,
        array $options = [],
        $siblingId = null,
        $prepend = false
    ) {
        $this->actions[self::GROUP_ADD][] = [
            self::ADD,
            __FUNCTION__,
            [$id, $parentId, $blockType, $options, $siblingId, $prepend]
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($id)
    {
        $this->actions[self::GROUP_REMOVE][] = [self::REMOVE, __FUNCTION__, [$id]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function move($id, $parentId = null, $siblingId = null, $prepend = false)
    {
        $this->actions[self::GROUP_ADD][] = [self::MOVE, __FUNCTION__, [$id, $parentId, $siblingId, $prepend]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addAlias($alias, $id)
    {
        $this->actions[self::GROUP_ADD][] = [self::ADD_ALIAS, __FUNCTION__, [$alias, $id]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeAlias($alias)
    {
        $this->actions[self::GROUP_REMOVE][] = [self::REMOVE_ALIAS, __FUNCTION__, [$alias]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setOption($id, $optionName, $optionValue)
    {
        $this->actions[self::GROUP_ADD][] = [self::SET_OPTION, __FUNCTION__, [$id, $optionName, $optionValue]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function appendOption($id, $optionName, $optionValue)
    {
        $this->actions[self::GROUP_ADD][] = [self::APPEND_OPTION, __FUNCTION__, [$id, $optionName, $optionValue]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function subtractOption($id, $optionName, $optionValue)
    {
        $this->actions[self::GROUP_ADD][] = [self::SUBTRACT_OPTION, __FUNCTION__, [$id, $optionName, $optionValue]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceOption($id, $optionName, $oldOptionValue, $newOptionValue)
    {
        $this->actions[self::GROUP_ADD][] =
            [self::REPLACE_OPTION, __FUNCTION__, [$id, $optionName, $oldOptionValue, $newOptionValue]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeOption($id, $optionName)
    {
        $this->actions[self::GROUP_ADD][] = [self::REMOVE_OPTION, __FUNCTION__, [$id, $optionName]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function changeBlockType($id, $blockType, $optionsCallback = null)
    {
        $this->actions[self::GROUP_ADD][] = [
            self::CHANGE_BLOCK_TYPE,
            __FUNCTION__,
            [$id, $blockType, $optionsCallback]
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setBlockTheme($themes, $id = null)
    {
        $this->actions[self::GROUP_ADD][] = [self::SET_BLOCK_THEME, __FUNCTION__, [$themes, $id]];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->rawLayoutBuilder->clear();
        $this->actions    = [];
        $this->addCounter = 0;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getNumberOfAddedItems()
    {
        return $this->addCounter;
    }

    /**
     * {@inheritdoc}
     */
    public function applyChanges(ContextInterface $context, $finalize = false)
    {
        $this->addCounter = 0;
        $this->item       = new LayoutItem($this->rawLayoutBuilder, $context);
        try {
            $total = $this->calculateActionCount();
            if ($total !== 0) {
                $this->executeAllActions($finalize);
                if ($finalize) {
                    $this->removeNotImportantRemainingActions();
                    // check that all scheduled actions have been performed
                    if ($this->calculateActionCount()) {
                        throw $this->createFailureException();
                    }
                }
            }

            $this->item = null;
        } catch (\Exception $e) {
            $this->item = null;
            throw $e;
        }
    }

    /**
     * Returns the total number of actions in all groups
     *
     * @return int
     */
    protected function calculateActionCount()
    {
        $counter = 0;
        foreach ($this->actions as $actions) {
            $counter += count($actions);
        }

        return $counter;
    }

    /**
     * Executes actions from all groups
     *
     * @param boolean $finalize
     */
    protected function executeAllActions($finalize)
    {
        $this->executeAddActions();
        if ($finalize) {
            $this->executeAdaptiveAddActions();
        }
        $this->executeRemoveActions();
    }

    /**
     * Checks if there are any not executed actions and remove actions which are not important
     */
    protected function removeNotImportantRemainingActions()
    {
        // remove remaining 'move' actions
        if (!empty($this->actions[self::GROUP_ADD])) {
            foreach ($this->actions[self::GROUP_ADD] as $key => $action) {
                if ($action[0] === self::MOVE) {
                    unset($this->actions[self::GROUP_ADD][$key]);
                }
            }
        }
        // remove remaining 'remove' actions if there are no any 'add' actions
        if (!empty($this->actions[self::GROUP_REMOVE]) && empty($this->actions[self::GROUP_ADD])) {
            unset($this->actions[self::GROUP_REMOVE]);
        }
    }

    /**
     * Executes all add new items related actions like
     *  * add
     *  * move
     *  * addAlias
     *  * setOption
     *  * appendOption
     *  * subtractOption
     *  * replaceOption
     *  * removeOption
     *  * changeBlockType
     */
    protected function executeAddActions()
    {
        if (!empty($this->actions[self::GROUP_ADD])) {
            $this->executeDependedActions(self::GROUP_ADD);
        }
    }

    /**
     * Executes all add new items related actions which were skipped by {@see executeAddActions} method,
     * but may be executed after removing/modifying some arguments
     */
    protected function executeAdaptiveAddActions()
    {
        // Here are several special rules:
        // 1) the siblingId argument in the 'add' action is "optional", this means that
        //    if it is not possible to add an item near to the sibling item due to it does not exist
        //    we should try to execute such 'add' action without siblingId argument
        // 2) the siblingId argument in the 'move' action is "optional", this means that
        //    if it is not possible to locate an item near to the sibling item due to it does not exist
        //    we should try to execute such 'move' action without siblingId argument
        $continue = true;
        while ($continue && !empty($this->actions[self::GROUP_ADD])) {
            $continue = false;
            foreach ($this->actions[self::GROUP_ADD] as $key => $action) {
                if (self::ADD === $action[0] && $action[2][4]) {
                    // remember siblingId for the case if the removing of it does not allow to execute the action
                    $siblingId = $this->actions[self::GROUP_ADD][$key][2][4];
                    // remove siblingId
                    $this->actions[self::GROUP_ADD][$key][2][4] = null;
                    // try to execute the action without siblingId
                    if (0 !== $this->executeDependedActions(self::GROUP_ADD)) {
                        $continue = true;
                        break;
                    } else {
                        // restore siblingId if the action was not executed
                        $this->actions[self::GROUP_ADD][$key][2][4] = $siblingId;
                    }
                } elseif (self::MOVE === $action[0] && $action[2][2]) {
                    // remember siblingId for the case if the removing of it does not allow to execute the action
                    $siblingId = $this->actions[self::GROUP_ADD][$key][2][2];
                    // remove siblingId
                    $this->actions[self::GROUP_ADD][$key][2][2] = null;
                    // try to execute the action without siblingId
                    if (0 !== $this->executeDependedActions(self::GROUP_ADD)) {
                        $continue = true;
                        break;
                    } else {
                        // restore siblingId if the action was not executed
                        $this->actions[self::GROUP_ADD][$key][2][2] = $siblingId;
                    }
                }
            }
        }
    }

    /**
     * Executes all remove items related actions like
     *  * remove
     *  * removeAlias
     */
    protected function executeRemoveActions()
    {
        if (!empty($this->actions[self::GROUP_REMOVE])) {
            $this->executeActions(self::GROUP_REMOVE);
        }
    }

    /**
     * Checks whether an action is ready to execute
     *
     * @param string $key  The action key
     * @param array  $args The action arguments
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function isActionReadyToExecute($key, $args)
    {
        switch ($key) {
            case self::ADD:
                $parentId  = $args[1];
                $siblingId = $args[4];

                return
                    !$parentId
                    || (
                        $this->rawLayoutBuilder->has($parentId)
                        && (!$siblingId || $this->rawLayoutBuilder->isParentFor($parentId, $siblingId))
                    );
            case self::MOVE:
                list($id, $parentId, $siblingId) = $args;

                return
                    (!$id || $this->rawLayoutBuilder->has($id))
                    && (!$parentId || $this->rawLayoutBuilder->has($parentId))
                    && (
                        !$siblingId
                        || ($parentId && $this->rawLayoutBuilder->isParentFor($parentId, $siblingId))
                        || (!$parentId && $this->rawLayoutBuilder->has($siblingId))
                    );
            case self::REMOVE:
            case self::SET_OPTION:
            case self::APPEND_OPTION:
            case self::SUBTRACT_OPTION:
            case self::REPLACE_OPTION:
            case self::REMOVE_OPTION:
            case self::CHANGE_BLOCK_TYPE:
                $id = $args[0];

                return !$id || $this->rawLayoutBuilder->has($id);
            case self::SET_BLOCK_THEME:
                $id = $args[1];

                return (!$id && !$this->rawLayoutBuilder->isEmpty()) || $this->rawLayoutBuilder->has($id);
            case self::ADD_ALIAS:
                $id = $args[1];

                return !$id || $this->rawLayoutBuilder->has($id);
            case self::REMOVE_ALIAS:
                $alias = $args[0];

                return !$alias || $this->rawLayoutBuilder->hasAlias($alias);
        }

        return true;
    }

    /**
     * Executes actions from the given group
     * Use this method if the group does not contain depended each other actions
     *
     * @param string $group
     *
     * @return int The number of executed actions
     */
    protected function executeActions($group)
    {
        $executeCounter = 0;
        reset($this->actions[$group]);
        while (list($key, $action) = each($this->actions[$group])) {
            if ($this->isActionReadyToExecute($action[0], $action[2])) {
                $this->executeAction($action);
                unset($this->actions[$group][$key]);
                $executeCounter++;
            }
        }

        return $executeCounter;
    }

    /**
     * Executes depended actions from the given group
     * Use this method if the group can contain depended each other actions
     * This method guarantee that all actions are executed in the order they are registered
     *
     * @param string $group
     *
     * @return int The number of executed actions
     */
    protected function executeDependedActions($group)
    {
        $executeCounter = 0;
        $continue       = true;
        while ($continue) {
            $continue    = false;
            $hasExecuted = false;
            $hasSkipped  = false;
            reset($this->actions[$group]);
            while (list($key, $action) = each($this->actions[$group])) {
                if ($this->isActionReadyToExecute($action[0], $action[2])) {
                    $this->executeAction($action);
                    unset($this->actions[$group][$key]);
                    $executeCounter++;
                    $hasExecuted = true;
                    if ($hasSkipped) {
                        // start execution from the begin
                        $continue = true;
                        break;
                    }
                } else {
                    $hasSkipped = true;
                    if ($hasExecuted) {
                        // start execution from the begin
                        $continue = true;
                        break;
                    }
                }
            }
        }

        return $executeCounter;
    }

    /**
     * @param array $action
     */
    protected function executeAction($action)
    {
        list($key, $name, $args) = $action;
        call_user_func_array([$this->rawLayoutBuilder, $name], $args);

        switch ($key) {
            case self::ADD:
                $this->addCounter++;
                $this->item->initialize($args[0]);
                $this->registry->updateLayout($args[0], $this, $this->item);
                break;
            case self::ADD_ALIAS:
                $this->item->initialize($this->rawLayoutBuilder->resolveId($args[1]), $args[0]);
                $this->registry->updateLayout($args[0], $this, $this->item);
                break;
            case self::MOVE:
                $this->addCounter++;
                break;
        }
    }

    /**
     * @return Exception\DeferredUpdateFailureException
     */
    protected function createFailureException()
    {
        $exActions = [];
        foreach ($this->actions as $actions) {
            foreach ($actions as $action) {
                $exActions[] = [
                    'name' => $action[1],
                    'args' => isset($action[2]) ? $action[2] : []
                ];
            }
        }

        return new Exception\DeferredUpdateFailureException(
            sprintf(
                'Failed to apply scheduled changes. %d action(s) cannot be applied.',
                count($exActions)
            ),
            $exActions,
            [$this, 'convertActionArgsToString']
        );
    }

    /**
     * @param array $action
     *
     * @return string|null
     */
    public function convertActionArgsToString(array $action)
    {
        switch ($action['name']) {
            case 'add':
            case 'addAlias':
                // for add: "id, parentId"
                // for addAlias: "alias, id"
                return sprintf('%s, %s', $action['args'][0], $action['args'][1]);
            case 'setBlockTheme':
                // "id"
                return sprintf('%s', $action['args'][1]);
        }

        // use default args to string converter which does the following:
        // - if args array is empty returns empty string
        // - otherwise, convert the first argument to string
        // @see Exception\DeferredUpdateFailureException
        return null;
    }
}
