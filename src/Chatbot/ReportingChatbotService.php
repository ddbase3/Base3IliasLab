<?php declare(strict_types=1);

namespace Base3IliasLab\Chatbot;

use Chatbot\Service\ChatbotService;

class ReportingChatbotService extends ChatbotService {

	private string $configDir = __DIR__ . '/../../local/Chatbot/';

	public static function getName(): string {
		return 'reportingchatbotservice';
	}

	protected function getBasePromptFile(): string {
		return $this->configDir . 'reporting-baseprompt.json';
	}

        protected function getSystemPromptFile(): string {
                return $this->configDir . 'reporting-systemprompt.txt';
        }

        protected function getAgentFlowFile(): string {
                return $this->configDir . 'reporting-agentflow.json';
        }

	protected function getSuggestionPromptFile(): string {
		return $this->configDir . 'suggestion-systemprompt.txt';
	}

        protected function getSuggestionFlowFile(): string {
                return $this->configDir . 'suggestion-agentflow.json';
        }
}
