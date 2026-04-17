<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Actions;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

#[AsWorkflowNode(key: 'run_command', type: NodeType::Action, label: 'Run Command')]
final class RunCommandAction extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'command_type', 'type' => 'select', 'label' => 'Command Type', 'required' => true, 'options' => ['artisan', 'shell']],
            ['key' => 'command', 'type' => 'string', 'label' => 'Command', 'required' => true, 'supports_expression' => true],
            ['key' => 'arguments', 'type' => 'keyvalue', 'label' => 'Arguments', 'required' => false, 'supports_expression' => true],
            ['key' => 'timeout', 'type' => 'integer', 'label' => 'Timeout (seconds)', 'required' => false],
            ['key' => 'working_directory', 'type' => 'string', 'label' => 'Working Directory', 'required' => false, 'supports_expression' => true],
            ['key' => 'include_output', 'type' => 'boolean', 'label' => 'Include command output in result', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'command_result.exit_code', 'type' => 'integer', 'label' => 'Exit Code'],
                ['key' => 'command_result.success', 'type' => 'boolean', 'label' => 'Success'],
                ['key' => 'command_result.output', 'type' => 'string', 'label' => 'Command Output'],
            ],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $this->validateAllowed($config);

        if ($config['command_type'] === 'shell' && ! config('workflow-automation.run_command.shell_enabled', true)) {
            throw new RuntimeException(
                'Shell commands are disabled. Set WORKFLOW_SHELL_ENABLED=true or update config to enable.'
            );
        }

        $results = [];

        foreach ($input->items as $item) {
            try {
                $result = match ($config['command_type']) {
                    'artisan' => $this->executeArtisan($config),
                    'shell' => $this->executeShell($config),
                    default => throw new InvalidArgumentException("Unsupported command type: {$config['command_type']}"),
                };

                $resultItem = array_merge($item, [
                    'command_result' => [
                        'exit_code' => $result['exit_code'],
                        'success' => $result['exit_code'] === 0,
                    ],
                ]);

                if ($config['include_output'] ?? false) {
                    $resultItem['command_result']['output'] = $result['output'];
                    if (! empty($result['error_output'])) {
                        $resultItem['command_result']['error_output'] = $result['error_output'];
                    }
                }

                $results[] = $resultItem;
            } catch (Throwable $e) {
                return NodeOutput::ports([
                    'main' => $results,
                    'error' => [array_merge($item, ['error' => $e->getMessage()])],
                ]);
            }
        }

        return NodeOutput::main($results);
    }

    private function executeArtisan(array $config): array
    {
        $arguments = $config['arguments'] ?? [];

        $exitCode = Artisan::call($config['command'], $arguments);
        $output = Artisan::output();

        return [
            'exit_code' => $exitCode,
            'output' => mb_trim($output),
            'error_output' => '',
        ];
    }

    private function executeShell(array $config): array
    {
        $command = $config['command'];
        $timeout = $config['timeout'] ?? 60;
        $cwd = $config['working_directory'] ?? base_path();

        $process = Process::fromShellCommandline($command, $cwd);
        $process->setTimeout($timeout);

        if (! empty($config['arguments'])) {
            $env = [];
            foreach ($config['arguments'] as $key => $value) {
                $env[$key] = $value;
            }
            $process->setEnv($env);
        }

        $process->run();

        return [
            'exit_code' => $process->getExitCode(),
            'output' => mb_trim($process->getOutput()),
            'error_output' => mb_trim($process->getErrorOutput()),
        ];
    }

    private function validateAllowed(array $config): void
    {
        $allowedCommands = config('workflow-automation.run_command.allowed_commands', []);

        if (empty($allowedCommands)) {
            return;
        }

        $command = $config['command'];

        foreach ($allowedCommands as $pattern) {
            if ($pattern === $command) {
                return;
            }

            if (str_contains($pattern, '*') && fnmatch($pattern, $command)) {
                return;
            }
        }

        throw new RuntimeException(
            "Command '{$command}' is not in the allowed commands list. "
            .'Add it to config(\'workflow-automation.run_command.allowed_commands\').'
        );
    }
}
