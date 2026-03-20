<?php

namespace App\Services\ControlPlane;

class ControlPlaneToolRegistry
{
    /**
     * Return all available tool definitions in Anthropic tool-use format.
     *
     * @return array<int, array{name: string, description: string, input_schema: array}>
     */
    public function tools(): array
    {
        return [
            ...$this->agentTools(),
            ...$this->skillTools(),
            ...$this->executionTools(),
            ...$this->systemTools(),
            ...$this->projectTools(),
        ];
    }

    /**
     * Get a flat map of tool name => definition for quick lookup.
     *
     * @return array<string, array>
     */
    public function toolMap(): array
    {
        $map = [];
        foreach ($this->tools() as $tool) {
            $map[$tool['name']] = $tool;
        }

        return $map;
    }

    protected function agentTools(): array
    {
        return [
            [
                'name' => 'list_agents',
                'description' => 'List all agents in the system with their names, roles, and status. Returns a summary of each agent.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
            [
                'name' => 'create_agent',
                'description' => 'Create a new agent with the given name and role. Returns the created agent.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'The name for the new agent',
                        ],
                        'role' => [
                            'type' => 'string',
                            'description' => 'The role/purpose of the agent (e.g., "code-reviewer", "documentation-writer")',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Optional description of what the agent does',
                        ],
                        'model' => [
                            'type' => 'string',
                            'description' => 'LLM model to use (e.g., "claude-sonnet-4-6"). Defaults to claude-sonnet-4-6.',
                        ],
                    ],
                    'required' => ['name', 'role'],
                ],
            ],
            [
                'name' => 'restart_agent',
                'description' => 'Restart an agent process by its ID. Only applicable to agents with running daemon processes.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'agent_id' => [
                            'type' => 'integer',
                            'description' => 'The ID of the agent to restart',
                        ],
                    ],
                    'required' => ['agent_id'],
                ],
            ],
            [
                'name' => 'stop_agent',
                'description' => 'Stop an agent process by its ID. Only applicable to agents with running daemon processes.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'agent_id' => [
                            'type' => 'integer',
                            'description' => 'The ID of the agent to stop',
                        ],
                    ],
                    'required' => ['agent_id'],
                ],
            ],
            [
                'name' => 'toggle_agent',
                'description' => 'Enable or disable an agent for a specific project.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => [
                            'type' => 'integer',
                            'description' => 'The project ID',
                        ],
                        'agent_id' => [
                            'type' => 'integer',
                            'description' => 'The agent ID',
                        ],
                        'enabled' => [
                            'type' => 'boolean',
                            'description' => 'Whether to enable (true) or disable (false) the agent',
                        ],
                    ],
                    'required' => ['project_id', 'agent_id', 'enabled'],
                ],
            ],
        ];
    }

    protected function skillTools(): array
    {
        return [
            [
                'name' => 'list_skills',
                'description' => 'List all skills for a given project. Returns skill names, descriptions, and models.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => [
                            'type' => 'integer',
                            'description' => 'The project ID to list skills for',
                        ],
                    ],
                    'required' => ['project_id'],
                ],
            ],
            [
                'name' => 'search_skills',
                'description' => 'Search for skills across all projects by name, tag, or keyword.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query string',
                        ],
                        'tags' => [
                            'type' => 'string',
                            'description' => 'Comma-separated list of tags to filter by',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'create_skill',
                'description' => 'Create a new skill in a project with a name, description, and prompt body.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => [
                            'type' => 'integer',
                            'description' => 'The project ID to create the skill in',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'The name of the skill',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'A short description of what the skill does',
                        ],
                        'body' => [
                            'type' => 'string',
                            'description' => 'The prompt/instruction body of the skill (markdown)',
                        ],
                        'model' => [
                            'type' => 'string',
                            'description' => 'LLM model to use. Defaults to project default.',
                        ],
                    ],
                    'required' => ['project_id', 'name', 'body'],
                ],
            ],
            [
                'name' => 'run_skill_test',
                'description' => 'Run a quick test of a skill with a sample user message. Returns the LLM response.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'skill_id' => [
                            'type' => 'integer',
                            'description' => 'The skill ID to test',
                        ],
                        'user_message' => [
                            'type' => 'string',
                            'description' => 'The test message to send to the skill',
                        ],
                    ],
                    'required' => ['skill_id', 'user_message'],
                ],
            ],
        ];
    }

    protected function executionTools(): array
    {
        return [
            [
                'name' => 'start_execution',
                'description' => 'Start an agent execution run on a project with an objective/input message.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => [
                            'type' => 'integer',
                            'description' => 'The project ID',
                        ],
                        'agent_id' => [
                            'type' => 'integer',
                            'description' => 'The agent ID to run',
                        ],
                        'input' => [
                            'type' => 'string',
                            'description' => 'The objective or input message for the execution',
                        ],
                    ],
                    'required' => ['project_id', 'agent_id', 'input'],
                ],
            ],
            [
                'name' => 'list_recent_runs',
                'description' => 'List recent execution runs, optionally filtered by project or agent. Shows status, timing, and token usage.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => [
                            'type' => 'integer',
                            'description' => 'Optional project ID to filter by',
                        ],
                        'agent_id' => [
                            'type' => 'integer',
                            'description' => 'Optional agent ID to filter by',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of runs to return (default 10, max 50)',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'list_failures',
                'description' => 'List recent failed execution runs with their error messages.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => [
                            'type' => 'integer',
                            'description' => 'Optional project ID to filter by',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of failures to return (default 10)',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'cancel_run',
                'description' => 'Cancel a running execution by its ID.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'run_id' => [
                            'type' => 'integer',
                            'description' => 'The execution run ID to cancel',
                        ],
                    ],
                    'required' => ['run_id'],
                ],
            ],
        ];
    }

    protected function systemTools(): array
    {
        return [
            [
                'name' => 'view_diagnostics',
                'description' => 'View system diagnostics including database status, queue health, storage usage, and configuration checks.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
            [
                'name' => 'provider_health',
                'description' => 'Check the health and availability of all configured LLM providers (Anthropic, OpenAI, Gemini, Ollama, etc.).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
            [
                'name' => 'fleet_status',
                'description' => 'Get a high-level overview of the entire system: total projects, agents, skills, recent executions, and active processes.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    protected function projectTools(): array
    {
        return [
            [
                'name' => 'list_projects',
                'description' => 'List all projects with their names, paths, and skill/agent counts.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
            [
                'name' => 'switch_project',
                'description' => 'Switch the current session context to focus on a specific project. Subsequent commands will default to this project.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => [
                            'type' => 'integer',
                            'description' => 'The project ID to switch to',
                        ],
                    ],
                    'required' => ['project_id'],
                ],
            ],
            [
                'name' => 'view_graph',
                'description' => 'View the dependency/composition graph for a project, showing how agents, skills, and workflows connect.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => [
                            'type' => 'integer',
                            'description' => 'The project ID to view the graph for',
                        ],
                    ],
                    'required' => ['project_id'],
                ],
            ],
        ];
    }
}
