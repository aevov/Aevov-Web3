<?php
/**
 * Smart Contract - Pattern-Based Contracts
 *
 * Implements smart contract execution engine with state management,
 * event triggers, and gas-like resource limiting for pattern-based
 * decentralized operations.
 *
 * @package AevovPatternSyncProtocol
 * @subpackage Blockchain
 * @since 1.0.0
 */

namespace APS\Blockchain;

use APS\Core\Logger;
use APS\Decentralized\DistributedLedger;
use APS\Pattern\PatternStorage;

class SmartContract {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Distributed ledger
     *
     * @var DistributedLedger
     */
    private $ledger;

    /**
     * Pattern storage
     *
     * @var PatternStorage
     */
    private $patternStorage;

    /**
     * Contract state storage
     *
     * @var array
     */
    private $contractStates;

    /**
     * Contract code registry
     *
     * @var array
     */
    private $contracts;

    /**
     * Event listeners
     *
     * @var array
     */
    private $eventListeners;

    /**
     * Gas limit for contract execution
     *
     * @var int
     */
    private $gasLimit;

    /**
     * Gas price per operation
     *
     * @var array
     */
    private $gasPrices;

    /**
     * Execution context
     *
     * @var array
     */
    private $executionContext;

    /**
     * Contract states
     */
    const STATE_CREATED = 'created';
    const STATE_DEPLOYED = 'deployed';
    const STATE_ACTIVE = 'active';
    const STATE_PAUSED = 'paused';
    const STATE_TERMINATED = 'terminated';

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct($config = []) {
        $this->logger = Logger::get_instance();
        $this->ledger = new DistributedLedger();
        $this->patternStorage = new PatternStorage();

        $this->contractStates = [];
        $this->contracts = [];
        $this->eventListeners = [];

        $this->gasLimit = $config['gas_limit'] ?? 1000000;
        $this->gasPrices = [
            'storage' => 100,
            'compute' => 10,
            'pattern_match' => 50,
            'event_emit' => 20,
            'external_call' => 200
        ];

        $this->executionContext = [
            'caller' => null,
            'gas_remaining' => 0,
            'block_number' => 0,
            'timestamp' => 0
        ];

        $this->logger->log('info', 'SmartContract engine initialized');
    }

    /**
     * Deploy a new smart contract
     *
     * @param array $contract_definition Contract definition
     * @param array $init_params Initialization parameters
     * @return array Deployment result
     */
    public function deployContract($contract_definition, $init_params = []) {
        // Validate contract definition
        if (!$this->validateContractDefinition($contract_definition)) {
            return [
                'success' => false,
                'error' => 'Invalid contract definition'
            ];
        }

        $contract_id = $this->generateContractId($contract_definition);

        $this->logger->log('info', 'Deploying smart contract', [
            'contract_id' => $contract_id,
            'name' => $contract_definition['name'] ?? 'unnamed'
        ]);

        try {
            // Initialize contract state
            $initial_state = $this->initializeContractState($contract_definition, $init_params);

            // Store contract
            $this->contracts[$contract_id] = [
                'id' => $contract_id,
                'definition' => $contract_definition,
                'state_id' => $initial_state['id'],
                'status' => self::STATE_DEPLOYED,
                'deployed_at' => time(),
                'deployer' => $this->executionContext['caller'] ?? 'system',
                'execution_count' => 0,
                'total_gas_used' => 0
            ];

            // Record deployment on ledger
            $this->ledger->newTransaction(
                'system',
                $contract_id,
                0 // No token transfer for deployment
            );

            // Emit deployment event
            $this->emitEvent('ContractDeployed', [
                'contract_id' => $contract_id,
                'deployer' => $this->contracts[$contract_id]['deployer']
            ]);

            $this->logger->log('info', 'Contract deployed successfully', [
                'contract_id' => $contract_id
            ]);

            return [
                'success' => true,
                'contract_id' => $contract_id,
                'state_id' => $initial_state['id']
            ];

        } catch (\Exception $e) {
            $this->logger->log('error', 'Contract deployment failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute contract function
     *
     * @param string $contract_id Contract ID
     * @param string $function Function name
     * @param array $params Function parameters
     * @param array $options Execution options (caller, gas_limit, etc.)
     * @return array Execution result
     */
    public function executeContract($contract_id, $function, $params = [], $options = []) {
        if (!isset($this->contracts[$contract_id])) {
            return [
                'success' => false,
                'error' => 'Contract not found'
            ];
        }

        $contract = $this->contracts[$contract_id];

        // Check contract status
        if ($contract['status'] !== self::STATE_DEPLOYED && $contract['status'] !== self::STATE_ACTIVE) {
            return [
                'success' => false,
                'error' => 'Contract not active',
                'status' => $contract['status']
            ];
        }

        // Initialize execution context
        $this->initializeExecutionContext($contract_id, $options);

        $this->logger->log('info', 'Executing contract function', [
            'contract_id' => $contract_id,
            'function' => $function
        ]);

        try {
            // Get contract state
            $state = $this->getContractState($contract['state_id']);

            // Execute function
            $result = $this->executeFunctionWithGasLimit(
                $contract['definition'],
                $function,
                $params,
                $state
            );

            // Update contract state
            if ($result['state_changed']) {
                $this->updateContractState($contract['state_id'], $result['new_state']);
            }

            // Update contract metrics
            $this->contracts[$contract_id]['execution_count']++;
            $this->contracts[$contract_id]['total_gas_used'] += $result['gas_used'];

            // Record execution on ledger
            $this->ledger->newTransaction(
                $this->executionContext['caller'],
                $contract_id,
                $result['gas_used'] // Gas cost
            );

            $this->logger->log('info', 'Contract execution completed', [
                'contract_id' => $contract_id,
                'function' => $function,
                'gas_used' => $result['gas_used']
            ]);

            return [
                'success' => true,
                'result' => $result['return_value'],
                'gas_used' => $result['gas_used'],
                'events' => $result['events'] ?? []
            ];

        } catch (\Exception $e) {
            $this->logger->log('error', 'Contract execution failed', [
                'contract_id' => $contract_id,
                'function' => $function,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'gas_used' => $this->executionContext['gas_remaining']
            ];
        }
    }

    /**
     * Execute function with gas limit enforcement
     *
     * @param array $contract_definition Contract definition
     * @param string $function Function name
     * @param array $params Parameters
     * @param array $state Current state
     * @return array Execution result
     */
    private function executeFunctionWithGasLimit($contract_definition, $function, $params, $state) {
        $initial_gas = $this->executionContext['gas_remaining'];
        $state_changed = false;
        $new_state = $state;
        $return_value = null;
        $events = [];

        // Find function in contract definition
        $function_def = null;
        if (isset($contract_definition['functions'][$function])) {
            $function_def = $contract_definition['functions'][$function];
        }

        if (!$function_def) {
            throw new \Exception('Function not found: ' . $function);
        }

        // Execute function body
        if (isset($function_def['code']) && is_callable($function_def['code'])) {
            // Callable function
            $function_context = [
                'state' => $state,
                'params' => $params,
                'contract' => $this,
                'emit_event' => function($event_name, $event_data) use (&$events) {
                    $this->consumeGas('event_emit');
                    $events[] = ['name' => $event_name, 'data' => $event_data];
                },
                'pattern_match' => function($pattern_id, $data) {
                    $this->consumeGas('pattern_match');
                    return $this->patternStorage->get_pattern($pattern_id);
                }
            ];

            $return_value = call_user_func($function_def['code'], $function_context);

            // Check if state was modified
            if ($function_context['state'] !== $state) {
                $state_changed = true;
                $new_state = $function_context['state'];
            }

        } elseif (isset($function_def['pattern_based'])) {
            // Pattern-based execution
            $return_value = $this->executePatternBasedFunction($function_def, $params, $state);
        } else {
            // Simple value return
            $return_value = $function_def['default_return'] ?? null;
        }

        // Calculate gas used
        $gas_used = $initial_gas - $this->executionContext['gas_remaining'];

        return [
            'return_value' => $return_value,
            'state_changed' => $state_changed,
            'new_state' => $new_state,
            'gas_used' => $gas_used,
            'events' => $events
        ];
    }

    /**
     * Execute pattern-based function
     *
     * @param array $function_def Function definition
     * @param array $params Parameters
     * @param array $state Current state
     * @return mixed Result
     */
    private function executePatternBasedFunction($function_def, $params, $state) {
        $this->consumeGas('pattern_match');

        $pattern_rules = $function_def['pattern_rules'] ?? [];

        foreach ($pattern_rules as $rule) {
            // Match pattern
            if ($this->matchPattern($rule['pattern'], $params)) {
                // Execute action
                return $this->executeAction($rule['action'], $params, $state);
            }
        }

        return $function_def['default_return'] ?? null;
    }

    /**
     * Match pattern against data
     *
     * @param array $pattern Pattern definition
     * @param mixed $data Data to match
     * @return bool Match result
     */
    private function matchPattern($pattern, $data) {
        // Simple pattern matching
        if (isset($pattern['type'])) {
            if (!isset($data['type']) || $data['type'] !== $pattern['type']) {
                return false;
            }
        }

        if (isset($pattern['conditions'])) {
            foreach ($pattern['conditions'] as $key => $value) {
                if (!isset($data[$key]) || $data[$key] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Execute action
     *
     * @param mixed $action Action definition
     * @param array $params Parameters
     * @param array $state State
     * @return mixed Action result
     */
    private function executeAction($action, $params, $state) {
        $this->consumeGas('compute');

        if (is_callable($action)) {
            return call_user_func($action, $params, $state);
        }

        return $action;
    }

    /**
     * Validate contract definition
     *
     * @param array $contract_definition Contract definition
     * @return bool Valid
     */
    private function validateContractDefinition($contract_definition) {
        // Check required fields
        if (!isset($contract_definition['name'])) {
            return false;
        }

        if (!isset($contract_definition['functions'])) {
            return false;
        }

        // Validate functions
        foreach ($contract_definition['functions'] as $function_name => $function_def) {
            if (!is_array($function_def)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Initialize contract state
     *
     * @param array $contract_definition Contract definition
     * @param array $init_params Initialization parameters
     * @return array Initial state
     */
    private function initializeContractState($contract_definition, $init_params) {
        $state_id = $this->generateStateId();

        $initial_state = [
            'id' => $state_id,
            'data' => $init_params,
            'created_at' => time(),
            'updated_at' => time(),
            'version' => 1
        ];

        // Execute constructor if defined
        if (isset($contract_definition['constructor'])) {
            $constructor = $contract_definition['constructor'];

            if (is_callable($constructor)) {
                $initial_state['data'] = call_user_func($constructor, $init_params);
            }
        }

        $this->contractStates[$state_id] = $initial_state;

        return $initial_state;
    }

    /**
     * Get contract state
     *
     * @param string $state_id State ID
     * @return array State
     */
    private function getContractState($state_id) {
        return $this->contractStates[$state_id] ?? [
            'id' => $state_id,
            'data' => [],
            'created_at' => time(),
            'updated_at' => time(),
            'version' => 0
        ];
    }

    /**
     * Update contract state
     *
     * @param string $state_id State ID
     * @param array $new_state New state data
     * @return void
     */
    private function updateContractState($state_id, $new_state) {
        $this->consumeGas('storage');

        $current_state = $this->getContractState($state_id);
        $current_state['data'] = $new_state;
        $current_state['updated_at'] = time();
        $current_state['version']++;

        $this->contractStates[$state_id] = $current_state;
    }

    /**
     * Initialize execution context
     *
     * @param string $contract_id Contract ID
     * @param array $options Execution options
     * @return void
     */
    private function initializeExecutionContext($contract_id, $options) {
        $this->executionContext = [
            'contract_id' => $contract_id,
            'caller' => $options['caller'] ?? 'anonymous',
            'gas_remaining' => $options['gas_limit'] ?? $this->gasLimit,
            'block_number' => $this->ledger->lastBlock()['index'] ?? 0,
            'timestamp' => time()
        ];
    }

    /**
     * Consume gas for operation
     *
     * @param string $operation Operation type
     * @param int $multiplier Gas multiplier
     * @return void
     * @throws \Exception If out of gas
     */
    private function consumeGas($operation, $multiplier = 1) {
        $gas_cost = ($this->gasPrices[$operation] ?? 1) * $multiplier;

        if ($this->executionContext['gas_remaining'] < $gas_cost) {
            throw new \Exception('Out of gas');
        }

        $this->executionContext['gas_remaining'] -= $gas_cost;
    }

    /**
     * Emit event
     *
     * @param string $event_name Event name
     * @param array $event_data Event data
     * @return void
     */
    private function emitEvent($event_name, $event_data) {
        $event = [
            'name' => $event_name,
            'data' => $event_data,
            'timestamp' => time(),
            'contract_id' => $this->executionContext['contract_id'] ?? null
        ];

        $this->logger->log('debug', 'Event emitted', [
            'event' => $event_name,
            'contract_id' => $event['contract_id']
        ]);

        // Trigger listeners
        if (isset($this->eventListeners[$event_name])) {
            foreach ($this->eventListeners[$event_name] as $listener) {
                call_user_func($listener, $event);
            }
        }
    }

    /**
     * Register event listener
     *
     * @param string $event_name Event name
     * @param callable $listener Listener callback
     * @return void
     */
    public function addEventListener($event_name, $listener) {
        if (!isset($this->eventListeners[$event_name])) {
            $this->eventListeners[$event_name] = [];
        }

        $this->eventListeners[$event_name][] = $listener;

        $this->logger->log('debug', 'Event listener registered', [
            'event' => $event_name
        ]);
    }

    /**
     * Pause contract execution
     *
     * @param string $contract_id Contract ID
     * @return bool Success
     */
    public function pauseContract($contract_id) {
        if (!isset($this->contracts[$contract_id])) {
            return false;
        }

        $this->contracts[$contract_id]['status'] = self::STATE_PAUSED;

        $this->emitEvent('ContractPaused', ['contract_id' => $contract_id]);

        return true;
    }

    /**
     * Resume contract execution
     *
     * @param string $contract_id Contract ID
     * @return bool Success
     */
    public function resumeContract($contract_id) {
        if (!isset($this->contracts[$contract_id])) {
            return false;
        }

        $this->contracts[$contract_id]['status'] = self::STATE_ACTIVE;

        $this->emitEvent('ContractResumed', ['contract_id' => $contract_id]);

        return true;
    }

    /**
     * Terminate contract
     *
     * @param string $contract_id Contract ID
     * @return bool Success
     */
    public function terminateContract($contract_id) {
        if (!isset($this->contracts[$contract_id])) {
            return false;
        }

        $this->contracts[$contract_id]['status'] = self::STATE_TERMINATED;
        $this->contracts[$contract_id]['terminated_at'] = time();

        $this->emitEvent('ContractTerminated', ['contract_id' => $contract_id]);

        return true;
    }

    /**
     * Get contract info
     *
     * @param string $contract_id Contract ID
     * @return array|null Contract info
     */
    public function getContractInfo($contract_id) {
        return $this->contracts[$contract_id] ?? null;
    }

    /**
     * Generate contract ID
     *
     * @param array $contract_definition Contract definition
     * @return string Contract ID
     */
    private function generateContractId($contract_definition) {
        return hash('sha256', json_encode($contract_definition) . time() . wp_rand());
    }

    /**
     * Generate state ID
     *
     * @return string State ID
     */
    private function generateStateId() {
        return uniqid('state_', true);
    }

    /**
     * Get contract statistics
     *
     * @return array Statistics
     */
    public function getStatistics() {
        $total_executions = 0;
        $total_gas = 0;

        foreach ($this->contracts as $contract) {
            $total_executions += $contract['execution_count'] ?? 0;
            $total_gas += $contract['total_gas_used'] ?? 0;
        }

        return [
            'contracts_count' => count($this->contracts),
            'states_count' => count($this->contractStates),
            'total_executions' => $total_executions,
            'total_gas_used' => $total_gas,
            'gas_limit' => $this->gasLimit,
            'event_listeners' => count($this->eventListeners)
        ];
    }

    /**
     * Get all contracts
     *
     * @return array Contracts
     */
    public function getAllContracts() {
        return $this->contracts;
    }
}
